<?php

namespace SmashPig\PaymentProviders\PayPal\Tests;

use SmashPig\Core\Context;
use SmashPig\Core\DataStores\PendingDatabase;
use SmashPig\Core\GlobalConfiguration;
use SmashPig\Core\Http\EnumValidator;
use SmashPig\Core\Http\Request;
use SmashPig\Core\Http\Response;
use SmashPig\Core\ProviderConfiguration;
use SmashPig\CrmLink\Messages\SourceFields;
use SmashPig\PaymentProviders\PayPal\Job;
use SmashPig\PaymentProviders\PayPal\Listener;
use SmashPig\Tests\BaseSmashPigUnitTestCase;

/**
 * Test the IPN listener which receives messages, stores and processes them.
 * @group PayPal
 */
class CaptureIncomingMessageTest extends BaseSmashPigUnitTestCase {

	/**
	 * @var GlobalConfiguration
	 */
	public $config;

	/**
	 * @var ProviderConfiguration
	 */
	public $providerConfig;

	/**
	 * @var array
	 */
	// filename and the queue it should get dropped in
	public static $message_data = [
		'web_accept.json' => 'donations',
		'express_checkout.json' => 'donations',
		'express_checkout.json' => 'donations',
		'recurring_payment_profile_created.json' => 'recurring',
		'subscr_signup.json' => 'recurring',
		'subscr_cancel.json' => 'recurring',
		'subscr_payment.json' => 'recurring',
		'recurring_payment.json' => 'recurring',
		'refund.json' => 'refund',
		'refund_ec.json' => 'refund',
		'refund_recurring_ec.json' => 'refund',
		'chargeback_settlement.json' => 'refund',
		'chargeback_settlement_ec.json' => 'refund',
		'buyer_complaint.json' => 'refund',
		'refund_other.json' => 'refund',
		'refund_unauthorized_spoof.json' => 'refund',
		'refund_admin_fraud_reversal.json' => 'refund',
		'recurring_payment_suspended_due_to_max_failed_payment.json' => 'recurring',
		// this should not actually get written to
		// TODO 'new_case.json' => 'no-op',
	];

	public function setUp() : void {
		parent::setUp();
		$this->config = Context::get()->getGlobalConfiguration();
		$this->providerConfig = $this->setProviderConfiguration( 'paypal' );
	}

	public function tearDown() : void {
		$this->providerConfig->overrideObjectInstance( 'curl/wrapper', null );
		parent::tearDown();
	}

	public function messageProvider() {
		$messages = [];
		foreach ( self::$message_data as $file => $type ) {
			$payloadFile = __DIR__ . '/../Data/' . $file;
			$messageData = [
				'type' => $type,
				'payload' => json_decode(
					file_get_contents( $payloadFile ),
					true
				)
			];
			$transformedFile = str_replace( '.json', '_transformed.json', $payloadFile );
			if ( file_exists( $transformedFile ) ) {
				$messageData['transformed'] = json_decode(
					file_get_contents( $transformedFile ),
					true
				);
			}
			$messages[] = [ $messageData ];
		}
		return $messages;
	}

	private function capture( $msg ) {
		$request = new Request( $msg );
		$response = new Response;
		$listener = new Listener;
		return $listener->execute( $request, $response );
	}

	protected function getCurlMock( $returnString ) {
		$wrapper = $this->createMock( 'SmashPig\Core\Http\CurlWrapper' );
		$wrapper->method( 'execute' )
			->willReturn(
				[
					'body' => $returnString
				]
			);
		$this->providerConfig->overrideObjectInstance( 'curl/wrapper', $wrapper );
		return $wrapper;
	}

	/**
	 * @dataProvider messageProvider
	 */
	public function testCapture( $msg ) {
		$this->getCurlMock( 'VERIFIED' );
		$this->capture( $msg['payload'] );

		$jobQueue = $this->config->object( 'data-store/jobs-paypal' );
		$jobMessage = $jobQueue->pop();

		$this->assertEquals(
			$jobMessage['class'],
			'SmashPig\PaymentProviders\PayPal\Job'
		);

		$this->assertEquals( $jobMessage['payload'], $msg['payload'] );
	}

	public function testBlankMessage() {
		$this->capture( [] );
		$jobQueue = $this->config->object( 'data-store/jobs-paypal' );
		$this->assertNull( $jobQueue->pop() );
	}

	/**
	 * @dataProvider messageProvider
	 */
	public function testConsume( $msg ) {
		$this->getCurlMock( 'VERIFIED' );
		$this->capture( $msg['payload'] );

		$jobQueue = $this->config->object( 'data-store/jobs-paypal' );
		$jobMessage = $jobQueue->pop();

		$job = new Job();
		$job->payload = $jobMessage['payload'];

		$job->execute();

		$queue = $this->config->object( 'data-store/' . $msg['type'] );
		$message = $queue->pop();

		if ( $job->is_reject() ) {
			$this->assertEmpty( $message );
		} else {
			$this->assertNotEmpty( $message );
			if ( isset( $message['contribution_tracking_id'] ) ) {
				$ctId = $message['contribution_tracking_id'];
				$this->assertEquals(
					$ctId,
					substr( $message['order_id'], 0, strlen( $ctId ) )
				);
			}

			if ( isset( $message['supplemental_address_1'] ) ) {
				$this->assertNotEquals(
					$message['supplemental_address_1'],
					"{$message['first_name']} {$message['last_name']}"
				);
			}
			if ( isset( $msg['transformed'] ) ) {
				SourceFields::removeFromMessage( $message );
				$this->assertEquals( $msg['transformed'], $message );
			}
		}
	}

	/**
	 * @dataProvider messageProvider
	 */
	public function testConsumeWithPending( $msg ) {
		$this->getCurlMock( 'VERIFIED' );
		$this->capture( $msg['payload'] );

		$jobQueue = $this->config->object( 'data-store/jobs-paypal' );
		$jobMessage = $jobQueue->pop();

		$job = new Job();
		$job->payload = $jobMessage['payload'];

		$pendingMessage = null;
		$pdb = PendingDatabase::get();
		// We should combine details from the pending the IF
		// (a) there is enough info to search the pending table
		$hasTransformedDetails = (
			isset( $msg['transformed'] ) &&
			isset( $msg['transformed']['gateway'] ) &&
			isset( $msg['transformed']['order_id'] )
		);
		// and (b) the message is of a type that could create a new contact
		// i.e. a new donation
		$isPayment = ( $msg['type'] === 'donations' );
		// ...or a new recurring subscription
		$isNewRecurring = (
			$msg['type'] === 'recurring' &&
			(
				$msg['transformed']['txn_type'] === 'subscr_signup' ||
				$msg['transformed']['txn_type'] === 'subscr_payment'
			)
		);
		if (
			$hasTransformedDetails &&
			( $isPayment || $isNewRecurring )
		) {
			$pendingMessage = [
				'gateway' => $msg['transformed']['gateway'],
				'order_id' => $msg['transformed']['order_id'],
				'date' => 12345678,
				'opt_in' => 1,
			];
			SourceFields::addToMessage( $pendingMessage );
			$pdb->storeMessage( $pendingMessage );
		}

		$job->execute();

		$queue = $this->config->object( 'data-store/' . $msg['type'] );
		$message = $queue->pop();

		if ( $job->is_reject() ) {
			$this->assertEmpty( $message );
		} else {
			$this->assertNotEmpty( $message );
			// order_id should start with the ct_id
			if ( isset( $message['contribution_tracking_id'] ) ) {
				$ctId = $message['contribution_tracking_id'];
				$this->assertEquals(
					$ctId,
					substr( $message['order_id'], 0, strlen( $ctId ) )
				);
			}

			if ( isset( $message['supplemental_address_1'] ) ) {
				$this->assertNotEquals(
					$message['supplemental_address_1'],
					"{$message['first_name']} {$message['last_name']}"
				);
			}
			if ( isset( $msg['transformed'] ) ) {
				if ( $pendingMessage !== null ) {
					$msg['transformed']['opt_in'] = $pendingMessage['opt_in'];
				}
				SourceFields::removeFromMessage( $message );
				$this->assertEquals( $msg['transformed'], $message );
			}
		}
	}

	public function testFailedVerification() {
		$this->getCurlMock( 'INVALID' );
		$jobMessage = [ 'txn_type' => 'fail' ];
		$this->assertFalse( $this->capture( $jobMessage ) );
	}

	public function testRetryValidator() {
		$validator = new EnumValidator( [ 'INVALID', 'VERIFIED' ] );
		$response = [
			'status' => 200,
			'headers' => [],
			'body' => '<html><head><title>Fail</title></head><body>Oops</body></html>'
		];
		$this->assertTrue( $validator->shouldRetry( $response ) );
		$response['body'] = 'INVALID';
		$this->assertFalse( $validator->shouldRetry( $response ) );
		$response['body'] = 'VERIFIED';
		$this->assertFalse( $validator->shouldRetry( $response ) );
	}

	/**
	 * Test that txn_type subscr_modify is ignored
	 */
	public function testIgnore() {
		$this->getCurlMock( 'VERIFIED' );
		$payload = json_decode(
			file_get_contents( __DIR__ . '/../Data/subscr_modify.json' ),
			true
		);
		$this->capture( $payload );
		$jobQueue = $this->config->object( 'data-store/jobs-paypal' );
		$jobMessage = $jobQueue->pop();

		$job = new Job();
		$job->payload = $jobMessage['payload'];

		$success = $job->execute();
		// Job should succeed
		$this->assertTrue( $success );
		// ...but not send any messages
		foreach ( [ 'donations', 'recurring', 'refund' ] as $queue ) {
			$msg = $this->config->object( "data-store/$queue" )->pop();
			$this->assertNull( $msg );
		}
	}

	/**
	 * Test that likely GiveLively donations are tagged correctly
	 */
	public function testTagGiveLively() {
		$this->getCurlMock( 'VERIFIED' );
		$this->providerConfig->override( [ 'givelively-appeal' => 'TeddyBearsPicnic' ] );
		$payload = json_decode(
			file_get_contents( __DIR__ . '/../Data/give_lively.json' ),
			true
		);
		$this->capture( $payload );
		$jobQueue = $this->config->object( 'data-store/jobs-paypal' );
		$jobMessage = $jobQueue->pop();

		$job = new Job();
		$job->payload = $jobMessage['payload'];

		$success = $job->execute();
		// Job should succeed
		$this->assertTrue( $success );
		// And send one message to the donations queue but no others
		foreach ( [ 'recurring', 'refund' ] as $queue ) {
			$msg = $this->config->object( "data-store/$queue" )->pop();
			$this->assertNull( $msg );
		}
		$msg = $this->config->object( 'data-store/donations' )->pop();
		$this->assertEquals( 'TeddyBearsPicnic', $msg['direct_mail_appeal'] );
		$this->assertEquals( 'GiveLively', $msg['no_thank_you'] );
	}
}
