<?php

use SmashPig\Core\Context;
use SmashPig\Core\Http\Request;
use SmashPig\PaymentData\FinalStatus;
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
	private $jobsGravyQueue;

	private $refundQueue;
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

	public function testTrustlyPaymentMFailedMessageIsSentToRefund(): void {
		[ $request, $response ] = $this->getValidRequestResponseObjects();
		$message = json_decode( file_get_contents( __DIR__ . '/../Data/trustly-create-transaction-failed-message.json' ), true );
		$request->method( 'getRawRequest' )->willReturn( json_encode( $message ) );
		$this->mockApi->expects( $this->never() )
			->method( 'getTransaction' );
		$this->gravyListener->execute( $request, $response );
		$refundMessage = $this->refundQueue->pop();
		$jobsMessage = $this->jobsGravyQueue->pop();
		$normalized_details = ( new ResponseMapper() )->mapFromPaymentResponse( $message['target'] );

		$this->assertNotNull( $refundMessage, '1 message for the failed ACH payment shoud be queued to refund queue' );
		$this->assertNull( $jobsMessage, 'No message shoud be queued to jobs queue' );
		$this->assertEquals( $normalized_details['gateway_parent_id'], $refundMessage['gateway_parent_id'] );
		$this->assertEquals( $normalized_details['gateway_refund_id'], $refundMessage['gateway_refund_id'] );
		$this->assertEquals( $normalized_details['currency'], $refundMessage['currency'] );
		$this->assertEquals( $normalized_details['amount'], $refundMessage['amount'] );
		$this->assertEquals( 'chargeback', $refundMessage['type'] );
		$this->assertEquals( FinalStatus::COMPLETE, $refundMessage['status'] );
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
