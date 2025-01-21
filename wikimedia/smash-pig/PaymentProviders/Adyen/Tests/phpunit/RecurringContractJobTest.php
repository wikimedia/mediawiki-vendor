<?php namespace SmashPig\PaymentProviders\Adyen\Test;

use SmashPig\Core\DataStores\PendingDatabase;
use SmashPig\Core\DataStores\QueueWrapper;
use SmashPig\PaymentProviders\Adyen\ExpatriatedMessages\RecurringContract;
use SmashPig\PaymentProviders\Adyen\Jobs\RecurringContractJob;
use SmashPig\PaymentProviders\Adyen\Tests\BaseAdyenTestCase;

/**
 * Verify Adyen RecurringContractJob job functions
 *
 * @group Adyen
 */
class RecurringContractJobTest extends BaseAdyenTestCase {

	protected $pendingMessage;

	public function setUp() : void {
		parent::setUp();
		$pendingDatabase = PendingDatabase::get();
		$this->pendingMessage = json_decode(
			file_get_contents( __DIR__ . '/../Data/pending_ideal.json' ), true
		);
		$pendingDatabase->storeMessage( $this->pendingMessage );
	}

	public function testRecurringContractJob() {
		$donationsQueue = QueueWrapper::getQueue( 'donations' );
		$contractMessage = RecurringContract::getInstanceFromJSON(
			json_decode( file_get_contents( __DIR__ . '/../Data/recurringContract.json' ), true )
		);

		$job = new RecurringContractJob();
		$job->payload = RecurringContractJob::factory( $contractMessage )['payload'];
		$this->assertTrue( $job->execute() );

		$donorData = PendingDatabase::get()->fetchMessageByGatewayOrderId(
			'adyen', $contractMessage->merchantReference
		);

		$this->assertNull(
			$donorData,
			'RecordCaptureJob left donor data on pending queue'
		);

		$donationMessage = $donationsQueue->pop();
		$this->assertNotNull(
			$donationMessage,
			'RecordCaptureJob did not send donation message'
		);
		// can we use arraySubset yet?
		$sameKeys = array_intersect(
			array_keys( $donationMessage ),
			array_keys( $this->pendingMessage )
		);
		foreach ( $sameKeys as $key ) {
			if ( $key === 'gateway_txn_id' ) {
				$this->assertEquals(
					$contractMessage->getGatewayTxnId(), $donationMessage[$key],
					'RecordCaptureJob should have set gateway_txn_id'
				);
			} else {
				$this->assertEquals(
					$this->pendingMessage[$key],
					$donationMessage[$key],
					"Value of key $key mutated"
				);
			}
		}
	}
}
