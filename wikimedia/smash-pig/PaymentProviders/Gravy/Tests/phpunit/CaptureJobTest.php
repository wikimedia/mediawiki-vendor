<?php

use PHPQueue\Backend\PDO;
use SmashPig\Core\DataStores\PaymentsFraudDatabase;
use SmashPig\Core\DataStores\PendingDatabase;
use SmashPig\Core\DataStores\QueueWrapper;
use SmashPig\PaymentProviders\Gravy\Jobs\ProcessCaptureRequestJob;
use SmashPig\PaymentProviders\Gravy\Mapper\ResponseMapper;
use SmashPig\PaymentProviders\Gravy\Tests\BaseGravyTestCase;

/**
 * @group Gravy
 */
class CaptureJobTest extends BaseGravyTestCase {
	/**
	 * @var PendingDatabase
	 */
	protected $pendingDatabase;

	/**
	 * @var PDO
	 */
	protected $antifraudQueue;
	/**
	 * @var PaymentsFraudDatabase
	 */
	protected $fraudDatabase;

	public function setUp(): void {
		parent::setUp();

		$this->pendingDatabase = PendingDatabase::get();
		$pendingMessage = json_decode(
			file_get_contents( __DIR__ . '/../Data/pending.json' ), true
		);
		$this->pendingDatabase->storeMessage( $pendingMessage );
		$this->antifraudQueue = QueueWrapper::getQueue( 'payments-antifraud' );
		$this->fraudDatabase = PaymentsFraudDatabase::get();
	}

	/**
	 * For a legit donation, ProcessCaptureJob should leave donor data
	 * in the pending database, add an antifraud message, and return true.
	 */
	public function testSuccessfulCapture() {
		$authorizedTransaction = json_decode(
			file_get_contents( __DIR__ . '/../Data/approve-transaction.json' ), true
		);
		$capturedTransaction = json_decode(
			file_get_contents( __DIR__ . '/../Data/successful-transaction.json' ), true
		);

		$authorizedTransaction['avs_response_code'] = 'match';
		$authorizedTransaction['cvv_response_code'] = 'match';

		$normalizedResponse = ( new ResponseMapper() )->mapFromPaymentResponse( $authorizedTransaction );

		$this->mockApi->expects( $this->once() )
			->method( 'approvePayment' )
			->with( $normalizedResponse['gateway_txn_id'], [
				'amount' => $normalizedResponse['amount'] * 100
			] )
			->willReturn( $capturedTransaction );

		$job = new ProcessCaptureRequestJob();
		$job->payload = $normalizedResponse;
		$this->assertTrue( $job->execute() );

		$donorData = $this->pendingDatabase->fetchMessageByGatewayOrderId(
			'gravy', $normalizedResponse['order_id']
		);

		$this->assertNotNull(
			$donorData,
			'RequestCaptureJob did not leave donor data on pending queue'
		);
		$this->assertTrue(
			$donorData['captured'],
			'RequestCaptureJob did not mark donor data as captured'
		);

		$antifraudMessage = $this->antifraudQueue->pop();
		$this->assertNotNull(
			$antifraudMessage,
			'RequestCaptureJob did not send antifraud message'
		);
		$this->assertEquals(
			'process',
			$antifraudMessage['validation_action'],
			'Successful capture should get "process" validation action'
		);
	}

	/**
	 * When AVS and CVV scores push the donation over the review threshold,
	 * we should not capture the payment, but leave the donor details.
	 */
	public function testReviewThreshold() {
		$authorizedTransaction = json_decode(
			file_get_contents( __DIR__ . '/../Data/approve-transaction.json' ), true
		);

		$normalizedResponse = ( new ResponseMapper() )->mapFromPaymentResponse( $authorizedTransaction );

		$this->mockApi->expects( $this->never() )
			->method( 'approvePayment' );

		$job = new ProcessCaptureRequestJob();
		$job->payload = $normalizedResponse;
		$this->assertTrue( $job->execute() );
		$donorData = $this->pendingDatabase->fetchMessageByGatewayOrderId(
			'gravy', $normalizedResponse['order_id']
		);

		$this->assertNotNull(
			$donorData,
			'RequestCaptureJob did not leave donor data for review'
		);

		$this->assertArrayNotHasKey(
		  'captured',
		  $donorData,
		  'RequestCaptureJob marked donor data above review threshold as captured'
		);

		$antifraudMessage = $this->antifraudQueue->pop();
		$this->assertNotNull(
			$antifraudMessage,
			'RequestCaptureJob did not send antifraud message'
		);
		$this->assertEquals(
			'review',
			$antifraudMessage['validation_action'],
			'Suspicious auth should get "review" validation action'
		);
	}

	/**
	 * When AVS and CVV scores push the donation over the reject threshold,
	 * we should cancel the authorization and delete the donor details.
	 */
	public function testRejectThreshold() {
		$authorizedTransaction = json_decode(
			file_get_contents( __DIR__ . '/../Data/approve-transaction.json' ), true
		);
		$cancelledTransaction = json_decode(
			file_get_contents( __DIR__ . '/../Data/cancelled-transaction.json' ), true
		);

		$authorizedTransaction['avs_response_code'] = 'no_match';
		$authorizedTransaction['cvv_response_code'] = 'no_match';
		$normalizedResponse = ( new ResponseMapper() )->mapFromPaymentResponse( $authorizedTransaction );
		$this->mockApi->expects( $this->never() )
			->method( 'approvePayment' );

		$this->mockApi->expects( $this->once() )
			->method( 'cancelTransaction' )
			->with( $normalizedResponse['gateway_txn_id'] )
			->willReturn( $cancelledTransaction );

		$job = new ProcessCaptureRequestJob();
		$job->payload = $normalizedResponse;
		$this->assertTrue( $job->execute() );

		$donorData = $this->pendingDatabase->fetchMessageByGatewayOrderId(
			'gravy', $normalizedResponse['order_id']
		);
		$this->assertNull(
			$donorData,
			'RequestCaptureJob should delete fraudy donor data'
		);

		$antifraudMessage = $this->antifraudQueue->pop();
		$this->assertNotNull(
			$antifraudMessage,
			'RequestCaptureJob did not send antifraud message'
		);
		$this->assertEquals(
			'reject',
			$antifraudMessage['validation_action'],
			'Obvious fraud should get "reject" validation action'
		);
	}

	/**
	 * When two authorizations come in with the same merchant reference, we
	 * should cancel the second one and leave the donor details in pending.
	 */
	public function testDuplicateAuthorisation() {
		$authorizedTransaction = json_decode(
			file_get_contents( __DIR__ . '/../Data/approve-transaction.json' ), true
		);
		$capturedTransaction = json_decode(
			file_get_contents( __DIR__ . '/../Data/successful-transaction.json' ), true
		);
		$cancelledTransaction = json_decode(
			file_get_contents( __DIR__ . '/../Data/cancelled-transaction.json' ), true
		);

		$authorizedTransaction['avs_response_code'] = 'match';
		$authorizedTransaction['cvv_response_code'] = 'match';

		$this->mockApi->expects( $this->once() )
			->method( 'approvePayment' )
			->willReturn( $capturedTransaction );

		$normalizedResponse = ( new ResponseMapper() )->mapFromPaymentResponse( $authorizedTransaction );

		$job1 = new ProcessCaptureRequestJob();
		$job1->payload = $normalizedResponse;
		$job1->execute();

		$authorizedTransaction_2 = json_decode(
			file_get_contents( __DIR__ . '/../Data/approve-transaction.json' ), true
		);
		$authorizedTransaction_2['id'] = 'random-transaction-gr4vy';
		$normalizedResponse_2 = ( new ResponseMapper() )->mapFromPaymentResponse( $authorizedTransaction_2 );
		$this->mockApi->expects( $this->once() )
			->method( 'cancelTransaction' )
			->with( $normalizedResponse_2['gateway_txn_id'] )
			->willReturn( $cancelledTransaction );

		$job2 = new ProcessCaptureRequestJob();
		$job2->payload = $normalizedResponse_2;
		$this->assertTrue(
			$job2->execute(),
			'Duplicate auths should not clutter damage queue'
		);

		$this->assertNotNull(
			$this->pendingDatabase->fetchMessageByGatewayOrderId(
				'gravy', $normalizedResponse['order_id']
			),
			'Capture job should leave donor details in database'
		);
	}

	/**
	 * When we can't find donor details in pending, use the fraud score from
	 * fredge to decide whether to capture.
	 */
	public function testFredgeFallback() {
		$authorizedTransaction = json_decode(
			file_get_contents( __DIR__ . '/../Data/approve-transaction.json' ), true
		);
		$capturedTransaction = json_decode(
			file_get_contents( __DIR__ . '/../Data/successful-transaction.json' ), true
		);

		$authorizedTransaction['avs_response_code'] = 'match';
		$authorizedTransaction['cvv_response_code'] = 'match';

		$normalizedResponse = ( new ResponseMapper() )->mapFromPaymentResponse( $authorizedTransaction );
		$this->pendingDatabase->deleteMessage( [
			'gateway' => 'gravy',
			'order_id' => $normalizedResponse['order_id']
		] );

		$this->fraudDatabase->storeMessage( [
			'contribution_tracking_id' => 119223,
			'gateway' => 'gravy',
			'order_id' => $normalizedResponse['order_id'],
			'validation_action' => 'process',
			'user_ip' => '127.0.0.1',
			'payment_method' => 'cc',
			'risk_score' => 15,
			'server' => 'localhost',
			'date' => 1458060070,
		] );

		$this->mockApi->expects( $this->once() )
			->method( 'approvePayment' )
			->with( $normalizedResponse['gateway_txn_id'], [
				'amount' => $normalizedResponse['amount'] * 100
			] )
			->willReturn( $capturedTransaction );

		$job = new ProcessCaptureRequestJob();
		$job->payload = $normalizedResponse;
		$this->assertTrue( $job->execute() );

		$antifraudMessage = $this->antifraudQueue->pop();
		$this->assertNotNull(
			$antifraudMessage,
			'RequestCaptureJob did not send antifraud message'
		);
		$this->assertEquals(
			'process',
			$antifraudMessage['validation_action'],
			'Successful capture should get "process" validation action'
		);
	}
}
