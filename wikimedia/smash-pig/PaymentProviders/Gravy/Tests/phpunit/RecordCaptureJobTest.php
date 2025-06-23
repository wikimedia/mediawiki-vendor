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
	protected array $pendingMessage;

	public function setUp(): void {
		parent::setUp();
		$this->pendingDatabase = PendingDatabase::get();
	}

	public function tearDown(): void {
		$this->pendingDatabase->deleteMessage( $this->pendingMessage );
		parent::tearDown();
	}

	public function testRecordCapture() {
		$this->storePendingMessage( 'pending' );
		[ $transactionDetails, $donationMessage ] = $this->runJobAndGetDonationMessage();
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

	public function testRecordCaptureWithSparsePendingMessage() {
		$this->storePendingMessage( 'pending-sparse' );
		[ $transactionDetails, $donationMessage ] = $this->runJobAndGetDonationMessage();
		$this->assertEquals( $donationMessage['gateway_txn_id'], $transactionDetails->getGatewayTxnId() );
		$this->assertEquals( $donationMessage['backend_processor'], $transactionDetails->getBackendProcessor() );
		$this->assertEquals( $donationMessage['backend_processor_txn_id'], $transactionDetails->getBackendProcessorTransactionId() );
		$this->assertEquals( $donationMessage['payment_submethod'], $transactionDetails->getPaymentSubmethod() );
	}

	/**
	 * @param string $fileName
	 * @return void
	 * @throws \SmashPig\Core\DataStores\DataStoreException
	 * @throws \SmashPig\Core\SmashPigException
	 */
	public function storePendingMessage( string $fileName ): void {
		$contents = file_get_contents( __DIR__ . '/../Data/' . $fileName . '.json' );
		$this->pendingMessage = json_decode( $contents, true );
		$this->pendingMessage['captured'] = true;
		$this->pendingDatabase->storeMessage( $this->pendingMessage );
	}

	/**
	 * @return array
	 * @throws \PHPQueue\Exception\JobNotFoundException
	 * @throws \SmashPig\Core\ConfigurationKeyException
	 * @throws \SmashPig\Core\DataStores\DataStoreException
	 * @throws \SmashPig\Core\RetryableException
	 */
	public function runJobAndGetDonationMessage(): array {
		$donationsQueue = QueueWrapper::getQueue( 'donations' );
		$capturedTransactionMessage = json_decode(
			file_get_contents( __DIR__ . '/../Data/successful-transaction-capture-message.json' ),
			true
		);
		$capturedTransaction = json_decode(
			file_get_contents( __DIR__ . '/../Data/successful-transaction.json' ),
			true
		);

		$normalizedResponse = ( new ResponseMapper() )->mapFromPaymentResponse( $capturedTransaction );
		$normalizedMessage = ( new ResponseMapper() )->mapFromPaymentResponse( $capturedTransactionMessage['target'] );
		$transactionDetails = GravyGetLatestPaymentStatusResponseFactory::fromNormalizedResponse( $normalizedResponse );
		$job = new RecordCaptureJob();
		$job->payload = array_merge(
			[
				"eventDate" => $capturedTransactionMessage["created_at"]
			],
			$normalizedMessage
		);
		$this->mockApi->expects( $this->once() )
			->method( 'getTransaction' )
			->willReturn( $capturedTransaction );

		$this->assertTrue( $job->execute() );

		$donorData = $this->pendingDatabase->fetchMessageByGatewayOrderId(
			'gravy',
			$transactionDetails->getOrderId()
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
		return [ $transactionDetails, $donationMessage ];
	}
}
