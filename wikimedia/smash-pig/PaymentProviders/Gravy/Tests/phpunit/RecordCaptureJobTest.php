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
	protected array $pendingMessage = [];

	public function setUp(): void {
		parent::setUp();
		$this->pendingDatabase = PendingDatabase::get();
	}

	public function tearDown(): void {
		if ( $this->pendingMessage ) {
			$this->pendingDatabase->deleteMessage( $this->pendingMessage );
		}
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
		$this->assertEquals( $transactionDetails->getGatewayTxnId(), $donationMessage['gateway_txn_id'] );
		$this->assertEquals( $transactionDetails->getBackendProcessor(), $donationMessage['backend_processor'] );
		$this->assertEquals( $transactionDetails->getBackendProcessorTransactionId(), $donationMessage['backend_processor_txn_id'] );
		$this->assertEquals( $transactionDetails->getPaymentSubmethod(), $donationMessage['payment_submethod'] );

		$donorDetails = $transactionDetails->getDonorDetails();
		$this->assertEquals( $donorDetails->getFirstName(), $donationMessage['first_name'] );
		$this->assertEquals( $donorDetails->getLastName(), $donationMessage['last_name'] );
		$this->assertEquals( $donorDetails->getEmail(), $donationMessage['email'] );

		$billingAddress = $donorDetails->getBillingAddress();
		$this->assertEquals( $billingAddress->getCity(), $donationMessage['city'] );
		$this->assertEquals( $billingAddress->getCountryCode(), $donationMessage['country'] );
		$this->assertEquals( $billingAddress->getPostalCode(), $donationMessage['postal_code'] );
		$this->assertEquals( $billingAddress->getStateOrProvinceCode(), $donationMessage['state_province'] );
		$this->assertEquals( $billingAddress->getStreetAddress(), $donationMessage['street_address'] );
	}

	/**
	 * The backend_processor_txn_id should always be updated from the
	 * fresh API response, even if the pending message already has a value.
	 * This is because Gravy updates payment_service_transaction_id to
	 * (Adyen's pspReference) after capture. We map the correct ID (auth
	 * vs capture) to the backendProcessorTransactionID property in
	 * ResponseMapper::mapPaymentResponsePaymentService
	 */
	public function testOverwritesBackendProcessorTxnId(): void {
		$contents = file_get_contents( __DIR__ . '/../Data/pending.json' );
		$this->pendingMessage = json_decode( $contents, true );
		$this->pendingMessage['captured'] = true;
		$this->pendingMessage['payment_submethod'] = 'sepadirectdebit';
		$this->pendingMessage['backend_processor_txn_id'] = 'OLD_SEPA_TXN_ID';
		$this->pendingDatabase->storeMessage( $this->pendingMessage );

		[ $transactionDetails, $donationMessage ] = $this->runJobAndGetDonationMessage();

		$this->assertEquals(
			$transactionDetails->getBackendProcessorTransactionId(),
			$donationMessage['backend_processor_txn_id'],
			'SEPA backend_processor_txn_id should be overwritten with fresh value from API'
		);
	}

	/**
	 * Moto gifts have no pending record, so the job has to build the donation
	 * message from the webhook and a fresh look at the transaction instead.
	 */
	public function testRecordCaptureMotoTransaction(): void {
		$donationsQueue = QueueWrapper::getQueue( 'donations' );
		$motoMessage = json_decode(
			file_get_contents( __DIR__ . '/../Data/moto-transaction-capture-message.json' ),
			true
		);
		$capturedTransaction = json_decode(
			file_get_contents( __DIR__ . '/../Data/successful-transaction.json' ),
			true
		);
		$normalizedMessage = ( new ResponseMapper() )->mapFromPaymentResponse( $motoMessage['target'] );
		$job = $this->getMotoJob( $motoMessage, $normalizedMessage );

		$this->mockApi->expects( $this->once() )
			->method( 'getTransaction' )
			->willReturn( $capturedTransaction );

		$this->assertTrue( $job->execute() );

		$donationMessage = $donationsQueue->pop();
		$this->assertNotNull(
			$donationMessage,
			'RecordCaptureJob did not send a donation message for the moto transaction'
		);

		// Identity of the gift comes from the webhook.
		$this->assertEquals( 'gravy', $donationMessage['gateway'] );
		$this->assertEquals( $motoMessage['target']['id'], $donationMessage['gateway_txn_id'] );
		$this->assertEquals(
			$motoMessage['target']['external_identifier'], $donationMessage['order_id']
		);
		$this->assertEquals(
			strtotime( $motoMessage['created_at'] ), $donationMessage['date']
		);

		// The donor is identified by the CiviCRM contact ID in the metadata,
		// since a moto gift carries no donor details at all.
		$this->assertEquals(
			$motoMessage['target']['metadata']['cid'], $donationMessage['contact_id']
		);

		// Gravy sends minor units. The mapper converts them and the queue
		// round-trips the message through JSON, so gross arrives as a number
		// rather than the formatted string a pending record would carry.
		$this->assertEquals( $normalizedMessage['amount'], $donationMessage['gross'] );
		$this->assertEquals( $normalizedMessage['currency'], $donationMessage['currency'] );
		$this->assertEquals( $normalizedMessage['payment_method'], $donationMessage['payment_method'] );

		// Gift details that a donation form would normally have supplied.
		$metadata = $motoMessage['target']['metadata'];
		$this->assertEquals( $metadata['appeal'], $donationMessage['direct_mail_appeal'] );
		$this->assertEquals( $metadata['fund'], $donationMessage['restrictions'] );
		$this->assertEquals( $metadata['channel'], $donationMessage['channel'] );
		$this->assertEquals( $metadata['package'], $donationMessage['Gift_Data.Package'] );

		// Nothing upstream minted a contribution tracking ID, so the job does it.
		$this->assertIsNumeric( $donationMessage['contribution_tracking_id'] );
		$this->assertNotEmpty( $donationMessage['contribution_tracking_id'] );

		// Details the thin webhook body does not carry come from the API.
		$transactionDetails = GravyGetLatestPaymentStatusResponseFactory::fromNormalizedResponse(
			( new ResponseMapper() )->mapFromPaymentResponse( $capturedTransaction )
		);
		$this->assertEquals(
			$transactionDetails->getBackendProcessor(), $donationMessage['backend_processor']
		);
		$this->assertEquals(
			$transactionDetails->getPaymentSubmethod(), $donationMessage['payment_submethod']
		);
		$this->assertEquals(
			$transactionDetails->getPaymentServiceID(), $donationMessage['payment_service_id']
		);
	}

	/**
	 * Builds the job the way RecordCaptureJob::factory does for a moto capture.
	 *
	 * @param array $motoMessage
	 * @param array $normalizedMessage
	 * @return RecordCaptureJob
	 */
	protected function getMotoJob( array $motoMessage, array $normalizedMessage ): RecordCaptureJob {
		$job = new RecordCaptureJob();
		$job->payload = array_merge(
			[ 'eventDate' => $motoMessage['created_at'] ],
			$normalizedMessage
		);

		return $job;
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

		$donorData = $this->pendingDatabase->fetchUnresolvedMessageByGatewayOrderId(
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
