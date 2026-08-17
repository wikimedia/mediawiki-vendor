<?php

use PHPQueue\Interfaces\FifoQueueStore;
use SmashPig\Core\Context;
use SmashPig\Core\Http\Request;
use SmashPig\CrmLink\Messages\SourceFields;
use SmashPig\PaymentProviders\Gravy\GravyListener;
use SmashPig\PaymentProviders\Gravy\Jobs\DownloadReportJob;
use SmashPig\PaymentProviders\Gravy\Jobs\ProcessCaptureRequestJob;
use SmashPig\PaymentProviders\Gravy\Jobs\RecordCaptureJob;
use SmashPig\PaymentProviders\Gravy\Mapper\ResponseMapper;
use SmashPig\PaymentProviders\Gravy\Tests\BaseGravyTestCase;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ServerBag;

/**
 * @group Gravy
 */
class NotificationsTest extends BaseGravyTestCase {
	private FifoQueueStore $jobsGravyQueue;

	private FifoQueueStore $refundQueue;

	private FifoQueueStore $donationsModifyQueue;

	/**
	 * @var GravyListener
	 */
	private $gravyListener;

	public function setUp(): void {
		parent::setUp();
		$this->jobsGravyQueue = Context::get()->getGlobalConfiguration()
			->object( 'data-store/jobs-gravy' );
		$this->refundQueue = Context::get()->getGlobalConfiguration()
			->object( 'data-store/refund' );
		$this->donationsModifyQueue = Context::get()->getGlobalConfiguration()
			->object( 'data-store/donations-modify' );
		$this->gravyListener = $this->config->object( 'endpoints/listener' );
	}

	public function testMessageInvalidRequestEmptyHeader() {
		[ $request, $response ] = $this->getInvalidRequestResponseObjectsEmptyHeader();
		$response->expects( $this->once() )->method( 'setStatusCode' )->with( Response::HTTP_FORBIDDEN, 'Invalid authorization' );
		$request->method( 'getRawRequest' )->willReturn( " " );
		$result = $this->gravyListener->execute( $request, $response );

		$this->assertFalse( $result );
	}

	public function testMessageInvalidRequestInvalidAuthorizationValue() {
		[ $request, $response ] = $this->getInvalidRequestResponseObjectsInvalidAuth();
		$response->expects( $this->once() )->method( 'setStatusCode' )->with( Response::HTTP_FORBIDDEN, 'Invalid authorization' );
		$request->method( 'getRawRequest' )->willReturn( " " );
		$result = $this->gravyListener->execute( $request, $response );

		$this->assertFalse( $result );
	}

	public function testTransactionMessageValidRequestValidAuthorizationValue(): void {
		[ $request, $response ] = $this->getValidRequestResponseObjects();
		$message = json_decode( file_get_contents( __DIR__ . '/../Data/successful-transaction-authorize-message.json' ), true );
		$request->method( 'getRawRequest' )->willReturn( json_encode( $message ) );
		$this->mockApi->expects( $this->never() )
			->method( 'getTransaction' );
		$result = $this->gravyListener->execute( $request, $response );
		$this->assertTrue( $result );
	}

	public function testUnknownMessageType(): void {
		[ $request, $response ] = $this->getValidRequestResponseObjects();
		$message = json_decode( $this->getValidGravyTransactionMessage(), true );
		$message['target']['type'] = "Unknown";
		$request->method( 'getRawRequest' )->willReturn( json_encode( $message ) );
		$result = $this->gravyListener->execute( $request, $response );
		$this->assertFalse( $result );
	}

	public function testCapturedTransactionMessage(): void {
		[ $request, $response ] = $this->getValidRequestResponseObjects();
		$message = json_decode( file_get_contents( __DIR__ . '/../Data/successful-transaction-capture-message.json' ), true );
		$request->method( 'getRawRequest' )->willReturn( json_encode( $message ) );
		$this->mockApi->expects( $this->never() )
			->method( 'getTransaction' );
		$result = $this->gravyListener->execute( $request, $response );
		$queued_message = $this->jobsGravyQueue->pop();
		$this->assertEquals( RecordCaptureJob::class, $queued_message['class'] );
		$payload = array_merge(
				[
					"eventDate" => $message["created_at"]
				], ( new ResponseMapper() )->mapFromPaymentResponse( $message['target'] )
			);
		$this->assertSame( $payload, $queued_message['payload'] );
		$this->assertTrue( $result );
	}

	public function testAuthorizedTransactionMessage(): void {
		$providerConfig = Context::get()->getProviderConfiguration();
		$providerConfig->override(
			[ 'capture-from-ipn-listener' => true ]
		);
		[ $request, $response ] = $this->getValidRequestResponseObjects();
		$message = json_decode( file_get_contents( __DIR__ . '/../Data/successful-transaction-authorize-message.json' ), true );
		$request->method( 'getRawRequest' )->willReturn( json_encode( $message ) );
		$this->mockApi->expects( $this->never() )
			->method( 'getTransaction' );
		$result = $this->gravyListener->execute( $request, $response );
		$queued_message = $this->jobsGravyQueue->pop();
		$this->assertEquals( ProcessCaptureRequestJob::class, $queued_message['class'] );
		$payload = array_merge(
			[
				"eventDate" => $message["created_at"]
			], ( new ResponseMapper() )->mapFromPaymentResponse( $message['target'] )
		);
		$this->assertSame( $payload, $queued_message['payload'] );
		$this->assertTrue( $result );
	}

	public function testAuthorizedTransactionMessageNoCapture(): void {
		[ $request, $response ] = $this->getValidRequestResponseObjects();
		$message = json_decode( file_get_contents( __DIR__ . '/../Data/successful-transaction-authorize-message.json' ), true );
		$request->method( 'getRawRequest' )->willReturn( json_encode( $message ) );
		$this->mockApi->expects( $this->never() )
			->method( 'getTransaction' );
		$result = $this->gravyListener->execute( $request, $response );
		$queued_message = $this->jobsGravyQueue->pop();
		$this->assertNull( $queued_message );
		$this->assertTrue( $result );
	}

	public function testRefundMessageWithProcessingStatusIsSkipped(): void {
		[ $request, $response ] = $this->getValidRequestResponseObjects();
		$responseBody = json_decode( file_get_contents( __DIR__ . '/../Data/pending-refund.json' ), true );
		$message = json_decode( $this->getValidGravyRefundMessage(), true );
		$request->method( 'getRawRequest' )->willReturn( json_encode( $message ) );
		$this->mockApi->expects( $this->once() )
			->method( 'getRefund' )
			->willReturn( $responseBody );
		$result = $this->gravyListener->execute( $request, $response );
		$queued_message = $this->refundQueue->pop();
		$this->assertNull( $queued_message, "Queue message shoud be skipped due to pending refund IPN" );
	}

	public function testPaymentMethodMessageIsDropped(): void {
		[ $request, $response ] = $this->getValidRequestResponseObjects();
		$responseBody = file_get_contents( __DIR__ . '/../Data/payment-method-updated.json' );
		$request->method( 'getRawRequest' )->willReturn( $responseBody );
		$this->gravyListener->execute( $request, $response );
		$refundMessage = $this->refundQueue->pop();
		$jobsMessage = $this->jobsGravyQueue->pop();
		$this->assertNull( $refundMessage, 'No message shoud be queued to refund queue' );
		$this->assertNull( $jobsMessage, 'No message shoud be queued to jobs queue' );
	}

	public function testMonitoringIncidentMessageIsDropped(): void {
		[ $request, $response ] = $this->getValidRequestResponseObjects();
		$responseBody = file_get_contents( __DIR__ . '/../Data/monitoring-incident-closed.json' );
		$request->method( 'getRawRequest' )->willReturn( $responseBody );
		$result = $this->gravyListener->execute( $request, $response );

		$this->assertTrue( $result );
		$jobsMessage = $this->jobsGravyQueue->pop();
		$this->assertNull( $jobsMessage, 'No message should be queued to jobs queue' );
	}

	public function testPaymentMethodDeletedMessageIsQueued(): void {
		[ $request, $response ] = $this->getValidRequestResponseObjects();
		$responseBody = file_get_contents( __DIR__ . '/../Data/payment-method-deleted-paypal.json' );
		$request->method( 'getRawRequest' )->willReturn( $responseBody );
		$result = $this->gravyListener->execute( $request, $response );

		// The listener should return true (successful processing).
		$this->assertTrue( $result );

		// A new job message should be created for the deletion event.
		$jobsMessage = $this->jobsGravyQueue->pop();
		$this->assertNotNull( $jobsMessage, 'Deletion event should be queued to jobs queue' );
	}

	public function testTrustlyPaymentFailedMessageIsNotSentToRefund(): void {
		[ $request, $response ] = $this->getValidRequestResponseObjects();
		$message = json_decode( file_get_contents( __DIR__ . '/../Data/trustly-create-transaction-failed-message.json' ), true );
		$request->method( 'getRawRequest' )->willReturn( json_encode( $message ) );
		$this->mockApi->expects( $this->never() )
			->method( 'getTransaction' );
		$this->gravyListener->execute( $request, $response );
		$refundMessage = $this->refundQueue->pop();
		$jobsMessage = $this->jobsGravyQueue->pop();
		$modifyMessage = $this->donationsModifyQueue->pop();

		$this->assertNull( $refundMessage, 'No message for the failed ACH payment should be queued to refund queue' );
		$this->assertNull( $jobsMessage, 'No message should be queued to jobs queue' );
		$this->assertNull( $modifyMessage, 'No message should be queued to donations-modify queue' );
	}

	public function testTrustlyPaymentDeclinedMessageIsSentToDonationsModifyQueue(): void {
		[ $request, $response ] = $this->getValidRequestResponseObjects();
		$message = json_decode( file_get_contents( __DIR__ . '/../Data/trustly-create-transaction-declined-message.json' ), true );
		$request->method( 'getRawRequest' )->willReturn( json_encode( $message ) );
		$this->mockApi->expects( $this->never() )
			->method( 'getTransaction' );
		$this->gravyListener->execute( $request, $response );
		$refundMessage = $this->refundQueue->pop();
		$jobsMessage = $this->jobsGravyQueue->pop();
		$modifyMessage = $this->donationsModifyQueue->pop();

		$this->assertNull( $refundMessage, 'ACH declines should not go to the refund queue' );
		$this->assertNull( $jobsMessage, 'No message should be queued to jobs queue' );
		$this->assertNotNull( $modifyMessage );
		SourceFields::removeFromMessage( $modifyMessage );
		$this->assertEquals( [
			'contribution_status_id:name' => 'Cancelled',
			'gateway_txn_id' => '943bec45-7cab-4555-8ea1-def34c34fae9',
			'payment_method' => 'ach',
			'order_id' => 'order-1234',
			'gross_currency' => 'USD',
			'gross' => 12.99,
			'backend_processor' => 'trustly',
			'backend_processor_txn_id' => '1025610947',
			'date' => 1355309623,
			'gateway' => 'gravy',
			'reason' => 'canceled_payment_method',
			'can_retry' => false,
			'is_suspected_fraud' => true,
		], $modifyMessage );
	}

	public function testTrustlyPaymentInsufficientFundsMessageIsSentToDonationsModifyQueue(): void {
		[ $request, $response ] = $this->getValidRequestResponseObjects();
		$message = json_decode( file_get_contents( __DIR__ . '/../Data/trustly-create-transaction-declined-retryable-message.json' ), true );
		$request->method( 'getRawRequest' )->willReturn( json_encode( $message ) );
		$this->mockApi->expects( $this->never() )
			->method( 'getTransaction' );
		$this->gravyListener->execute( $request, $response );
		$refundMessage = $this->refundQueue->pop();
		$jobsMessage = $this->jobsGravyQueue->pop();
		$modifyMessage = $this->donationsModifyQueue->pop();

		$this->assertNull( $refundMessage, 'ACH declines should not go to the refund queue' );
		$this->assertNull( $jobsMessage, 'No message should be queued to jobs queue' );
		$this->assertNotNull( $modifyMessage );
		SourceFields::removeFromMessage( $modifyMessage );
		$this->assertEquals( [
			'contribution_status_id:name' => 'Cancelled',
			'gateway_txn_id' => '338b9bc1-ff9f-48b9-a66c-742380770e96',
			'payment_method' => 'ach',
			'order_id' => '1234.1',
			'gross_currency' => 'USD',
			'gross' => 25.00,
			'backend_processor' => 'trustly',
			'backend_processor_txn_id' => '567890',
			'date' => 1784939264,
			'gateway' => 'gravy',
			'reason' => 'insufficient_funds',
			'can_retry' => true,
			'is_suspected_fraud' => false,
		], $modifyMessage );
	}

	public function testRefundMessageComplete(): void {
		[ $request, $response ] = $this->getValidRequestResponseObjects();
		$responseBody = json_decode( file_get_contents( __DIR__ . '/../Data/successful-refund.json' ), true );
		$message = json_decode( $this->getValidGravyRefundMessage(), true );
		$request->method( 'getRawRequest' )->willReturn( json_encode( $message ) );

		$this->mockApi->expects( $this->once() )
			->method( 'getRefund' )
			->willReturn( $responseBody );

		$result = $this->gravyListener->execute( $request, $response );
		$queued_message = $this->refundQueue->pop();
		$normalized_details = ( new ResponseMapper() )->mapFromRefundPaymentResponse( $responseBody );
		unset( $normalized_details['raw_response'] );
		$normalized_details["date"] = strtotime( $message["created_at"] );

		$this->assertEquals( $normalized_details['gateway_parent_id'], $queued_message['gateway_parent_id'] );
		$this->assertEquals( $normalized_details['gateway_refund_id'], $queued_message['gateway_refund_id'] );
		$this->assertEquals( $normalized_details['currency'], $queued_message['currency'] );
		$this->assertEquals( $normalized_details['amount'], $queued_message['amount'] );
		$this->assertEquals( $normalized_details['type'], $queued_message['type'] );
		$this->assertEquals( $normalized_details['date'], $queued_message['date'] );
		$this->assertTrue( $result );
	}

	public function testRefundMessageCompleteThreeDecimalCurrency() {
		[ $request, $response ] = $this->getValidRequestResponseObjects();
		$responseBody = json_decode( file_get_contents( __DIR__ . '/../Data/successful-three-decimal-refund.json' ), true );
		$message = json_decode( $this->getValidGravyRefundMessage(), true );
		$request->method( 'getRawRequest' )->willReturn( json_encode( $message ) );

		$this->mockApi->expects( $this->once() )
			->method( 'getRefund' )
			->willReturn( $responseBody );

		$result = $this->gravyListener->execute( $request, $response );
		$queued_message = $this->refundQueue->pop();

		$normalized_details = ( new ResponseMapper() )->mapFromRefundPaymentResponse( $responseBody );
		unset( $normalized_details['raw_response'] );

		// Check the three decimals
		$this->assertEquals( $normalized_details['amount'], $responseBody['amount'] / 1000 );
		$this->assertTrue( $result );
	}

	public function testRefundMessageFailed(): void {
		[ $request, $response ] = $this->getValidRequestResponseObjects();

		// Create a failed refund webhook message by modifying the default one
		$testGravyWebhook = json_decode( $this->getValidGravyRefundMessage(), true );
		$testGravyWebhook['target']['id'] = '2d4ee558-43da-4dbe-9a6d-c47dd031b8bd';
		$testGravyWebhook['target']['transaction_id'] = 'd9ec899b-0f53-45a6-a2d2-79a448771299';
		$testGravyWebhook['target']['status'] = 'failed';

		$request->method( 'getRawRequest' )->willReturn( json_encode( $testGravyWebhook ) );

		$testGetRefundApiCallResponse = json_decode( file_get_contents( __DIR__ . '/../Data/failed-refund.json' ), true );

		$this->mockApi->expects( $this->once() )
		->method( 'getRefund' )
		->willReturn( $testGetRefundApiCallResponse );

		$result = $this->gravyListener->execute( $request, $response );

		// For failed refunds, no message should be queued
		$queued_message = $this->refundQueue->pop();

		$this->assertNull( $queued_message, 'Failed refunds should not be queued' );
		$this->assertTrue( $result, 'Listener should still return true for handled failed refunds' );
	}

	public function testRefundMessageDeclined(): void {
		[ $request, $response ] = $this->getValidRequestResponseObjects();

		// Create a declined refund webhook message by modifying the default one
		$testGravyWebhook = json_decode( $this->getValidGravyRefundMessage(), true );
		$testGravyWebhook['target']['id'] = '3e5ff669-54eb-5ecf-0b7e-d58ee142c9ce';
		$testGravyWebhook['target']['transaction_id'] = 'e0fd9aac-1064-56b7-b3e3-8a559882a3aa';
		$testGravyWebhook['target']['status'] = 'declined';

		$request->method( 'getRawRequest' )->willReturn( json_encode( $testGravyWebhook ) );

		$testGetRefundApiCallResponse = json_decode( file_get_contents( __DIR__ . '/../Data/declined-refund.json' ), true );

		$this->mockApi->expects( $this->once() )
		->method( 'getRefund' )
		->willReturn( $testGetRefundApiCallResponse );

		$result = $this->gravyListener->execute( $request, $response );

		// Declined refunds should not be queued - admins are notified by email instead
		$queued_message = $this->refundQueue->pop();

		$this->assertNull( $queued_message, 'Declined refunds should not be queued' );
		$this->assertTrue( $result, 'Listener should still return true for handled declined refunds' );
	}

	public function testReportExecutionMessage(): void {
		[ $request, $response ] = $this->getValidRequestResponseObjects();
		$reportExecutionResponseBody = json_decode( file_get_contents( __DIR__ . '/../Data/report-execution-successful.json' ), true );
		$generateReportUrlResponseBody = json_decode( file_get_contents( __DIR__ . '/../Data/generate-report-url-successful.json' ), true );

		$message = json_decode( $this->getValidGravyReportExecutionMessage(), true );
		$request->method( 'getRawRequest' )->willReturn( json_encode( $message ) );
		$this->mockApi->expects( $this->once() )
			->method( 'getReportExecutionDetails' )
			->willReturn( $reportExecutionResponseBody );

		$this->mockApi->expects( $this->once() )
			->method( 'generateReportDownloadUrl' )
			->willReturn( $generateReportUrlResponseBody );

		$result = $this->gravyListener->execute( $request, $response );
		$queued_message = $this->jobsGravyQueue->pop();
		$payload = $queued_message['payload'];
		$class = $queued_message['class'];
		$normalized_details = ( new ResponseMapper() )->mapFromGenerateReportUrlResponse( $generateReportUrlResponseBody );
		$this->assertEquals( DownloadReportJob::class, $class );
		$this->assertEquals( $normalized_details['expires'], $payload['expires'] );
		$this->assertEquals( $normalized_details['report_url'], $payload['report_url'] );
		$this->assertTrue( $result );
	}

	public function getValidRequestResponseObjects( string $request = " " ): array {
		return $this->getMockRequestResponseObjects( [
			"AUTHORIZATION" => "Basic " . base64_encode( $this->config->val( "accounts/webhook/username" ) . ":" . $this->config->val( "accounts/webhook/password" ) )
		] );
	}

	public function getInvalidRequestResponseObjectsEmptyHeader(): array {
		return $this->getMockRequestResponseObjects( [] );
	}

	public function getInvalidRequestResponseObjectsInvalidAuth(): array {
		return $this->getMockRequestResponseObjects( [
			'AUTHORIZATION' => 'Basic YWxhZGRpbjpvcGVuc2VzYW1l'
		] );
	}

	private function getMockRequestResponseObjects( array $headers ): array {
		$request = $this->getMockBuilder( Request::class )->disableOriginalConstructor()->getMock();
		$request->server = $this->getMockBuilder( ServerBag::class )->disableOriginalConstructor()->getMock();
		$request->server->method( 'getHeaders' )->willReturn( $headers );
		$response = $this->getMockBuilder( Response::class )->disableOriginalConstructor()->getMock();

		return [ $request, $response ];
	}

	private function getValidGravyTransactionMessage(): string {
		return '{"type":"event","id":"36d2c101-4db5-4afd-ba4b-8fd9b60764ab","created_at":"2024-07-22T19:56:22.973896+00:00",
        "target":{"type":"transaction","id":"b332ca0a-1dce-4ae6-b27b-04f70db8fae7"},"merchant_account_id":"default"}';
	}

	private function getValidGravyRefundMessage(): string {
		return '{"type":"event","id":"36d2c101-4db5-4afd-ba4b-8fd9b60764ab","created_at":"2024-07-22T19:56:22.973896+00:00",
        "target":{"type":"refund","id":"c88fcbc0-8070-481c-87e3-6c4d4a5c9219","transaction_id":"795c27e9d-6cc3-40f6-a359-1355c434c30d"},"merchant_account_id":"default"}';
	}

	private function getValidGravyReportExecutionMessage(): string {
		return '{"type":"event","id":"347901e1-8b53-42a4-951b-ec546a5078f1","created_at":"2012-12-12T10:53:43+00:00",
		"merchant_account_id":"default","target":{"type":"report-execution","id":"8d29457b-683a-49c4-8afd-800cd7117236"}}';
	}
}
