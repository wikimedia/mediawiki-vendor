<?php namespace SmashPig\PaymentProviders\Adyen\Test;

use PHPQueue\Backend\PDO;
use SmashPig\Core\DataStores\PaymentsFraudDatabase;
use SmashPig\Core\DataStores\PendingDatabase;
use SmashPig\Core\DataStores\QueueWrapper;
use SmashPig\PaymentProviders\Adyen\ExpatriatedMessages\Authorisation;
use SmashPig\PaymentProviders\Adyen\Jobs\ProcessCaptureRequestJob;
use SmashPig\PaymentProviders\Adyen\Tests\AdyenTestConfiguration;
use SmashPig\PaymentProviders\Adyen\Tests\BaseAdyenTestCase;

/**
 * Verify Adyen Capture job functions
 *
 * @group Adyen
 */
class CaptureJobTest extends BaseAdyenTestCase {
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
		$auth = Authorisation::getInstanceFromJSON(
			json_decode( file_get_contents( __DIR__ . '/../Data/auth.json' ), true )
		);

		$this->mockApi->expects( $this->once() )
			->method( 'approvePayment' )
			->with( [
				'amount' => 10,
				'currency' => 'USD',
				'gateway_txn_id' => '762895314225',
			] )
			->willReturn( AdyenTestConfiguration::getSuccessfulApproveResult() );

		$job = new ProcessCaptureRequestJob();
		$job->payload = ProcessCaptureRequestJob::factory( $auth )['payload'];
		$this->assertTrue( $job->execute() );

		$donorData = $this->pendingDatabase->fetchMessageByGatewayOrderId(
			'adyen', $auth->merchantReference
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
		$auth = Authorisation::getInstanceFromJSON(
			json_decode( file_get_contents( __DIR__ . '/../Data/auth.json' ), true )
		);

		$auth->avsResult = '1'; // Bad zip code pushes us over review

		$this->mockApi->expects( $this->never() )
			->method( 'approvePayment' );

		$job = new ProcessCaptureRequestJob();
		$job->payload = ProcessCaptureRequestJob::factory( $auth )['payload'];
		$this->assertTrue( $job->execute() );

		$donorData = $this->pendingDatabase->fetchMessageByGatewayOrderId(
			'adyen', $auth->merchantReference
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
		$auth = Authorisation::getInstanceFromJSON(
			json_decode( file_get_contents( __DIR__ . '/../Data/auth.json' ), true )
		);

		$auth->avsResult = '2'; // No match at all
		$auth->cvvResult = '2'; // CVV is also wrong

		$this->mockApi->expects( $this->never() )
			->method( 'approvePayment' );

		$this->mockApi->expects( $this->once() )
			->method( 'cancel' )
			->with( $auth->pspReference )
			->willReturn( AdyenTestConfiguration::getSuccessfulCancelResult() );

		$job = new ProcessCaptureRequestJob();
		$job->payload = ProcessCaptureRequestJob::factory( $auth )['payload'];
		$this->assertTrue( $job->execute() );

		$donorData = $this->pendingDatabase->fetchMessageByGatewayOrderId(
			'adyen', $auth->merchantReference
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
		$auth1 = Authorisation::getInstanceFromJSON(
			json_decode( file_get_contents( __DIR__ . '/../Data/auth.json' ), true )
		);

		$this->mockApi->expects( $this->once() )
			->method( 'approvePayment' )
			->willReturn( AdyenTestConfiguration::getSuccessfulApproveResult() );

		$job1 = new ProcessCaptureRequestJob();
		$job1->payload = ProcessCaptureRequestJob::factory( $auth1 )['payload'];
		$job1->execute();

		$auth2 = Authorisation::getInstanceFromJSON(
			json_decode( file_get_contents( __DIR__ . '/../Data/auth.json' ), true )
		);
		$auth2->pspReference = mt_rand( 1000000000, 10000000000 );

		$this->mockApi->expects( $this->once() )
			->method( 'cancel' )
			->with( $auth2->pspReference )
			->willReturn( AdyenTestConfiguration::getSuccessfulCancelResult() );

		$job2 = new ProcessCaptureRequestJob();
		$job2->payload = ProcessCaptureRequestJob::factory( $auth2 )['payload'];
		$this->assertTrue(
			$job2->execute(),
			'Duplicate auths should not clutter damage queue'
		);

		$this->assertNotNull(
			$this->pendingDatabase->fetchMessageByGatewayOrderId(
				'adyen', $auth1->merchantReference
			),
			'Capture job should leave donor details in database'
		);
	}

	/**
	 * When we can't find donor details in pending, use the fraud score from
	 * fredge to decide whether to capture.
	 */
	public function testFredgeFallback() {
		$auth = Authorisation::getInstanceFromJSON(
			json_decode( file_get_contents( __DIR__ . '/../Data/auth.json' ), true )
		);

		$this->pendingDatabase->deleteMessage( [
			'gateway' => 'adyen',
			'order_id' => $auth->merchantReference
		] );

		$this->fraudDatabase->storeMessage( [
			'contribution_tracking_id' => 119223,
			'gateway' => 'adyen',
			'order_id' => $auth->merchantReference,
			'validation_action' => 'process',
			'user_ip' => '127.0.0.1',
			'payment_method' => 'cc',
			'risk_score' => 15,
			'server' => 'localhost',
			'date' => 1458060070,
		] );

		$this->mockApi->expects( $this->once() )
			->method( 'approvePayment' )
			->with( [
				'amount' => 10,
				'currency' => 'USD',
				'gateway_txn_id' => '762895314225'
			] )
			->willReturn( AdyenTestConfiguration::getSuccessfulApproveResult() );

		$job = new ProcessCaptureRequestJob();
		$job->payload = ProcessCaptureRequestJob::factory( $auth )['payload'];
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
