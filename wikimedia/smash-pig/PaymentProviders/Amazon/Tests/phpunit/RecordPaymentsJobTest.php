<?php

namespace SmashPig\PaymentProviders\Amazon\Tests;

use SmashPig\Core\DataStores\PendingDatabase;
use SmashPig\Core\DataStores\QueueWrapper;
use SmashPig\Core\QueueConsumers\JobQueueConsumer;
use SmashPig\CrmLink\Messages\SourceFields;
use SmashPig\PaymentProviders\Amazon\ExpatriatedMessages\CaptureCompleted;
use SmashPig\PaymentProviders\Amazon\RecordPaymentJob;

/**
 * @group Amazon
 */
class RecordPaymentsJobTest extends AmazonTestCase {

	/**
	 * @var string
	 */
	protected $id;
	/**
	 * @var string
	 */
	protected $refId;

	public function setUp(): void {
		parent::setUp();
		$this->id = 'P01-0000555-5550000-C' . mt_rand( 10000, 99999 );
		$this->refId = null;
	}

	/**
	 * Set a random reference ID, like those that get generated for donations
	 * made via voice-control devices
	 */
	protected function setRandomRefId() {
		$this->refId = mt_rand( 100000000, 1000000000 ) . mt_rand( 100000000, 1000000000 );
	}

	protected function getPayload() {
		$values = $this->loadJson(
			__DIR__ . "/../Data/IPN/CaptureCompleted.json"
		);
		$values['CaptureDetails']['AmazonCaptureId'] = $this->id;
		if ( $this->refId ) {
			$values['CaptureDetails']['CaptureReferenceId'] = $this->refId;
		}
		$captureCompleted = new CaptureCompleted( $values );
		return $captureCompleted->getPayload();
	}

	public function testCreate() {
		$this->setRandomRefId();
		$expected = [
			'class' => '\SmashPig\PaymentProviders\Amazon\RecordPaymentJob',
			'payload' => [
				'currency' => 'USD',
				'date' => 1357002061,
				'fee' => '0.0',
				'gateway' => 'amazon',
				'gateway_status' => 'Completed',
				'gateway_txn_id' => $this->id,
				'gross' => '10.0',
				'order_id' => $this->refId,
				'payment_method' => 'amazon',
				'order_reference_id' => 'P01-0000555-5550000',
			],
		];
		$jobMessage = RecordPaymentJob::fromAmazonMessage(
			$this->getPayload()
		);
		$this->assertEquals( $expected, $jobMessage );
	}

	/**
	 * Make sure the job can combine details correctly from the pending
	 * database when a matching message exists
	 */
	public function testExecuteWithPendingMessage() {
		$db = PendingDatabase::get();
		$pendingMessage = [
			'gateway_txn_id' => false,
			'response' => false,
			'gateway_account' => 'default',
			'fee' => 0,
			'contribution_tracking_id' => '98765432',
			'utm_source' => 'B1819_0822_en4C_dsk_p1_lg_frm_cnt.no-LP.amazon',
			'utm_medium' => 'sitenotice',
			'utm_campaign' => 'C1819_en6C_dsk_FR',
			'language' => 'en',
			'email' => 'jgaitan@liberals.co',
			'first_name' => 'Jorge',
			'last_name' => 'Gaitán',
			'country' => 'US',
			'gateway' => 'amazon',
			'order_id' => '98765432-1',
			'recurring' => '',
			'payment_method' => 'amazon',
			'payment_submethod' => '',
			'currency' => 'USD',
			'gross' => '10.00',
			'user_ip' => '111.222.33.44',
			'date' => 1357002061,
			'source_name' => 'DonationInterface',
			'source_type' => 'payments',
			'source_host' => 'machine1003',
			'source_run_id' => 19930,
			'source_version' => '360bea8331523c73f86f49d6f7de263cbb93ddc9',
			'source_enqueued_time' => 1357002061
		];
		$db->storeMessage( $pendingMessage );
		$jobMessage = RecordPaymentJob::fromAmazonMessage(
			$this->getPayload()
		);
		$job = JobQueueConsumer::createJobObject(
			$jobMessage
		);
		$job->execute();
		$message = QueueWrapper::getQueue( 'donations' )->pop();
		$expected = $pendingMessage;
		$expected['fee'] = '0.0'; // weird, but doesn't matter
		$expected['gateway_status'] = 'Completed';
		$expected['gateway_txn_id'] = $this->id;
		SourceFields::removeFromMessage( $message );
		SourceFields::removeFromMessage( $expected );
		$this->assertEquals( $expected, $message );
		$dbMessage = $db->fetchMessageByGatewayOrderId( 'amazon', '98765432-1' );
		$this->assertNull( $dbMessage, 'Should delete pending db message' );
	}

	/**
	 * Make sure the job correctly looks up and combines donor details from
	 * the Amazon Pay API when no pending message exists.
	 */
	public function testExecuteWithLookup() {
		$this->setRandomRefId();
		$this->mockClient->returns['getOrderReferenceDetails'] = [ 'Alexa' ];
		$expected = [
			'date' => 1357002061,
			'gateway' => 'amazon',
			'gateway_txn_id' => $this->id,
			'gross' => '10.0',
			'currency' => 'USD',
			'gateway_status' => 'Completed',
			'payment_method' => 'amazon',
			'fee' => '0.0',
			'email' => 'jgaitan@liberals.co',
			'first_name' => 'Jorge',
			'last_name' => 'Gaitán',
			'order_id' => $this->refId,
			'street_address' => '123 Sunset Blvd',
			'postal_code' => '90210',
			'state_province' => 'CA',
			'country' => 'US',
			'city' => 'Los Angeles'
		];
		$jobMessage = RecordPaymentJob::fromAmazonMessage(
			$this->getPayload()
		);
		$job = JobQueueConsumer::createJobObject(
			$jobMessage
		);
		$job->execute();
		$message = QueueWrapper::getQueue( 'donations' )->pop();
		SourceFields::removeFromMessage( $message );
		$this->assertEquals( $expected, $message );
	}
}
