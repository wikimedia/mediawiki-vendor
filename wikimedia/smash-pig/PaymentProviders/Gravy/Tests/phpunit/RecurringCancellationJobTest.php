<?php

namespace SmashPig\PaymentProviders\Gravy\Tests\phpunit;

use SmashPig\Core\DataStores\QueueWrapper;
use SmashPig\PaymentProviders\Gravy\ExpatriatedMessages\PaymentMethodMessage;
use SmashPig\PaymentProviders\Gravy\GravyListener;
use SmashPig\PaymentProviders\Gravy\Jobs\RecurringCancellationJob;
use SmashPig\PaymentProviders\Gravy\Tests\BaseGravyTestCase;

/**
 * @group Gravy
 */
class RecurringCancellationJobTest extends BaseGravyTestCase {

	/**
	 * @var QueueWrapper
	 */
	protected $recurringQueue;

	public function setUp(): void {
		parent::setUp();
		$this->recurringQueue = QueueWrapper::getQueue( 'recurring' );
	}

	/**
	 * For a PayPal payment method deletion, RecurringCancellationJob should
	 * push a properly formatted message to the recurring queue and return true.
	 */
	public function testSuccessfulCancellation(): void {
		$paymentMethodData = json_decode(
			file_get_contents( __DIR__ . '/../Data/payment-method-deleted-paypal.json' ), true
		);

		// Create a test PaymentMethodMessage from the webhook notification fixture data
		$normalizedPaymentMethodData = ( new GravyListener )->mapFromWebhookMessage( $paymentMethodData );
		$paymentMethodMessage = $this->createTestPaymentMethodMessage( $normalizedPaymentMethodData );
		$job = new RecurringCancellationJob();
		$jobData = RecurringCancellationJob::factory( $paymentMethodMessage );
		$job->payload = $jobData['payload'];

		$this->assertTrue( $job->execute() );

		$recurringMessage = $this->recurringQueue->pop();
		$this->assertNotNull(
			$recurringMessage,
			'RecurringCancellationJob did not send message to recurring queue'
		);
		$this->assertEquals( 'gravy', $recurringMessage['gateway'] );
		$this->assertEquals( 'subscr_cancel', $recurringMessage['txn_type'] );
		$this->assertEquals( 'paypal', $recurringMessage['payment_method'] );
		$this->assertSame( '1', $recurringMessage['recurring'] );
		$this->assertEquals( $paymentMethodData['target']['id'], $recurringMessage['subscr_id'] );

		$expectedMessageDate = strtotime( $paymentMethodData['created_at'] );
		$this->assertEquals( $expectedMessageDate, $recurringMessage['date'] );
		$expectedCancelDate = strtotime( $paymentMethodData['created_at'] );
		$this->assertEquals( $expectedCancelDate, $recurringMessage['cancel_date'] );
	}

	/**
	 * Test that the factory method creates the correct payload format
	 */
	public function testFactoryMethod(): void {
		$paymentMethodData = json_decode(
			file_get_contents( __DIR__ . '/../Data/payment-method-deleted-paypal.json' ), true
		);

		// Create a test PaymentMethodMessage from the webhook notification fixture data
		$normalizedPaymentMethodData = ( new GravyListener )->mapFromWebhookMessage( $paymentMethodData );
		$paymentMethodMessage = $this->createTestPaymentMethodMessage( $normalizedPaymentMethodData );
		$jobData = RecurringCancellationJob::factory( $paymentMethodMessage );

		$this->assertEquals(
			RecurringCancellationJob::class,
			$jobData['class'],
			'Factory should return correct class name'
		);

		$payload = $jobData['payload'];

		$expectedPayload = [
			'gateway' => 'gravy',
			'txn_type' => 'subscr_cancel',
			'payment_method' => 'paypal',
			'recurring' => '1',
			'subscr_id' => $payload['subscr_id'],
			'date' => $payload['date'],
			'cancel_date' => $payload['cancel_date'],
		];

		$this->assertEquals( $expectedPayload,
			array_intersect_key( $payload, $expectedPayload ) );
	}

	/**
	 * Test that the job handles missing or invalid data gracefully
	 */
	public function testHandlesMissingData(): void {
		$paymentMethodData = json_decode(
			file_get_contents( __DIR__ . '/../Data/payment-method-deleted-paypal.json' ), true
		);

		// Create a test PaymentMethodMessage from the fixture data and then remove payment method details
		$normalizedPaymentMethodData = ( new GravyListener )->mapFromWebhookMessage( $paymentMethodData );
		$paymentMethodMessage = $this->createTestPaymentMethodMessage( $normalizedPaymentMethodData, true );
		$job = new RecurringCancellationJob();
		$jobData = RecurringCancellationJob::factory( $paymentMethodMessage );
		$job->payload = $jobData['payload'];

		$this->assertTrue( $job->execute() );

		$recurringMessage = $this->recurringQueue->pop();
		$this->assertNotNull(
			$recurringMessage,
			'Job should still process even with missing data'
		);
	}

	private function createTestPaymentMethodMessage( array $data, bool $missingDetails = false ): PaymentMethodMessage {
		$paymentMethodMessageData = $data;
		if ( $missingDetails === true ) {
			$paymentMethodMessageData['target']['details'] = [];
		}
		$paymentMethodMessage = new PaymentMethodMessage();
		$paymentMethodMessage->init( $paymentMethodMessageData );
		return $paymentMethodMessage;
	}
}
