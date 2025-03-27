<?php

use SmashPig\Core\DataStores\PendingDatabase;
use SmashPig\Core\DataStores\QueueWrapper;
use SmashPig\PaymentProviders\Gravy\Factories\GravyGetLatestPaymentStatusResponseFactory;
use SmashPig\PaymentProviders\Gravy\Jobs\RecordCaptureJob;
use SmashPig\PaymentProviders\Gravy\Mapper\ResponseMapper;
use SmashPig\PaymentProviders\Gravy\Tests\BaseGravyTestCase;

/**
 * @group Gravy
 */
class RecordCaptureJobTest extends BaseGravyTestCase {
	/**
	 * @var PendingDatabase
	 */
	protected $pendingDatabase;
	protected $pendingMessage;

	public function setUp(): void {
		parent::setUp();
		$this->pendingDatabase = PendingDatabase::get();
		$this->pendingMessage = json_decode(
			file_get_contents( __DIR__ . '/../Data/pending.json' ), true
		);
		$this->pendingMessage['captured'] = true;
		$this->pendingDatabase->storeMessage( $this->pendingMessage );
	}

	public function tearDown(): void {
		$this->pendingDatabase->deleteMessage( $this->pendingMessage );
		parent::tearDown();
	}

	public function testRecordCapture() {
		$donationsQueue = QueueWrapper::getQueue( 'donations' );
		$capturedTransaction = json_decode(
			file_get_contents( __DIR__ . '/../Data/successful-transaction.json' ), true
		);

		$normalizedResponse = ( new ResponseMapper() )->mapFromPaymentResponse( $capturedTransaction );
		$transactionDetails = GravyGetLatestPaymentStatusResponseFactory::fromNormalizedResponse( $normalizedResponse );
		$job = new RecordCaptureJob();
		$message = json_decode( $this->getValidGravyTransactionMessage(), true );
		$job->payload = array_merge(
			[
				"eventDate" => $message["created_at"]
			], $normalizedResponse
		);
		$this->assertTrue( $job->execute() );

		$donorData = $this->pendingDatabase->fetchMessageByGatewayOrderId(
			'gravy', $transactionDetails->getOrderId()
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
					$transactionDetails->getGatewayTxnId(), $donationMessage[$key],
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

	private function getValidGravyTransactionMessage(): string {
		return '{"type":"event","id":"36d2c101-4db5-4afd-ba4b-8fd9b60764ab","created_at":"2024-07-22T19:56:22.973896+00:00",
        "target":{"type":"transaction","id":"b332ca0a-1dce-4ae6-b27b-04f70db8fae7"},"merchant_account_id":"default"}';
	}
}
