<?php

namespace SmashPig\PaymentProviders\Adyen\Tests\phpunit;

use SmashPig\Core\Context;
use SmashPig\Core\Helpers\Base62Helper;
use SmashPig\Core\Http\Request;
use SmashPig\PaymentProviders\Adyen\AdyenRestListener;
use SmashPig\PaymentProviders\Adyen\NotificationSignatureValidator;
use SmashPig\PaymentProviders\Adyen\Tests\AdyenTestConfiguration;
use SmashPig\PaymentProviders\Adyen\Tests\BaseAdyenTestCase;
use Symfony\Component\HttpFoundation\Response;

/**
 * @group Adyen
 */
class NotificationRequestTest extends BaseAdyenTestCase {

	/**
	 * A key that isn't configured for any account
	 */
	private const INVALID_HMAC_KEY = 'A4D7082C892EB4297FCDFBE70863F037C3A993BC847DC329568D63A71A8EDBD2';

	private $jobsAdyenQueue;

	private $refundQueue;

	/**
	 * @var AdyenRestListener
	 */
	public $rest_listener;

	public function setUp(): void {
		parent::setUp();
		$this->jobsAdyenQueue = Context::get()->getGlobalConfiguration()
			->object( 'data-store/jobs-adyen' );

		$this->refundQueue = Context::get()->getGlobalConfiguration()
			->object( 'data-store/refund' );

		$this->rest_listener = $this->config->object( 'endpoints/listener' );

		$this->config->override( [
			'accounts' => [
				'WikimediaDonations' => [ 'webhook-hmac-key' => AdyenTestConfiguration::WEBHOOK_HMAC_KEY ],
			],
			'reject-notifications-without-hmac' => true,
		] );
	}

	private static function getFixture( string $fileName ): array {
		return json_decode( file_get_contents( __DIR__ . '/../Data/' . $fileName ), true );
	}

	/**
	 * Load a webhook fixture, sign each item with the test key and return it
	 * as a request body
	 */
	private function getSignedFixture( string $fileName ): string {
		return json_encode( $this->signWebhook( self::getFixture( $fileName ) ) );
	}

	private function signWebhook(
		array $webhook, string $key = AdyenTestConfiguration::WEBHOOK_HMAC_KEY
	): array {
		foreach ( $webhook['notificationItems'] as $index => $notification ) {
			$webhook['notificationItems'][$index]['NotificationRequestItem']['additionalData']['hmacSignature'] =
				NotificationSignatureValidator::calculateSignature( $notification['NotificationRequestItem'], $key );
		}
		return $webhook;
	}

	/**
	 * Send a webhook to the listener and return its output
	 */
	private function sendToListener( array $webhook, Response $response ): string {
		$request = $this->getMockBuilder( Request::class )->disableOriginalConstructor()
			->getMock();
		$request->method( 'getRawRequest' )->willReturn( json_encode( $webhook ) );
		ob_start();
		$this->rest_listener->execute( $request, $response );
		return ob_get_clean();
	}

	private function getResponseExpectingForbidden(): Response {
		$response = $this->getMockBuilder( Response::class )->disableOriginalConstructor()
			->getMock();
		$response->expects( $this->once() )->method( 'setStatusCode' )
			->with( Response::HTTP_FORBIDDEN, $this->anything() );
		return $response;
	}

	private function getResponseExpectingSuccess(): Response {
		$response = $this->getMockBuilder( Response::class )->disableOriginalConstructor()
			->getMock();
		$response->expects( $this->never() )->method( 'setStatusCode' );
		return $response;
	}

	/**
	 * Webhooks that queue a job in jobs-adyen when accepted. Tests that expect
	 * a rejection check that nothing was queued, which only means something
	 * for notifications that would otherwise queue a job.
	 */
	public static function jobQueueingNotificationProvider(): array {
		// This fixture is a single NotificationRequestItem rather than a whole
		// webhook, so wrap it like the ipn_*.json fixtures
		$autoRescue = [
			'live' => 'false',
			'notificationItems' => [ [
				'NotificationRequestItem' => self::getFixture( 'successful_auto_rescue_auth.json' ),
			] ],
		];

		return [
			'CAPTURE' => [ self::getFixture( 'ipn_Capture.json' ) ],
			'auto-rescue AUTHORISATION' => [ $autoRescue ],
			'REPORT_AVAILABLE' => [ self::getFixture( 'ipn_ReportAvailable.json' ) ],
		];
	}

	/**
	 * @dataProvider jobQueueingNotificationProvider
	 */
	public function testValidSignatureAccepted( array $webhook ): void {
		$output = $this->sendToListener( $this->signWebhook( $webhook ), $this->getResponseExpectingSuccess() );
		$this->assertStringContainsString( '[accepted]', $output );
		$this->assertNotNull( $this->jobsAdyenQueue->pop() );
	}

	/**
	 * @dataProvider jobQueueingNotificationProvider
	 */
	public function testInvalidSignatureRejected( array $webhook ): void {
		$output = $this->sendToListener(
			$this->signWebhook( $webhook, self::INVALID_HMAC_KEY ), $this->getResponseExpectingForbidden()
		);
		$this->assertStringNotContainsString( '[accepted]', $output );
		$this->assertNull( $this->jobsAdyenQueue->pop() );
	}

	/**
	 * @dataProvider jobQueueingNotificationProvider
	 */
	public function testUnsignedNotificationRejected( array $webhook ): void {
		unset( $webhook['notificationItems'][0]['NotificationRequestItem']['additionalData']['hmacSignature'] );
		$output = $this->sendToListener( $webhook, $this->getResponseExpectingForbidden() );
		$this->assertStringNotContainsString( '[accepted]', $output );
		$this->assertNull( $this->jobsAdyenQueue->pop() );
	}

	public function testNotificationForMerchantAccountNotInConfigRejected(): void {
		$webhook = self::getFixture( 'ipn_Capture.json' );
		$webhook['notificationItems'][0]['NotificationRequestItem']['merchantAccountCode'] = 'MerchantAccountNotInConfig';
		$output = $this->sendToListener( $this->signWebhook( $webhook ), $this->getResponseExpectingForbidden() );
		$this->assertStringNotContainsString( '[accepted]', $output );
		$this->assertNull( $this->jobsAdyenQueue->pop() );
	}

	/**
	 * An item with an invalid signature alongside a valid one rejects the whole
	 * request, so the valid item is not processed either. Adyen will retry it.
	 */
	public function testInvalidSignatureRejectsWholeRequest(): void {
		$webhook = $this->signWebhook( self::getFixture( 'ipn_Capture.json' ) );
		$invalid = $this->signWebhook( self::getFixture( 'ipn_Capture.json' ), self::INVALID_HMAC_KEY );
		$webhook['notificationItems'][] = $invalid['notificationItems'][0];

		$output = $this->sendToListener( $webhook, $this->getResponseExpectingForbidden() );
		$this->assertStringNotContainsString( '[accepted]', $output );
		$this->assertNull( $this->jobsAdyenQueue->pop() );
	}

	public function testItemThatIsNotAnObjectSkipped(): void {
		$webhook = [ 'notificationItems' => [ [ 'NotificationRequestItem' => 'not an object' ] ] ];
		$output = $this->sendToListener( $webhook, $this->getResponseExpectingSuccess() );
		$this->assertStringContainsString( '[accepted]', $output );
		$this->assertNull( $this->jobsAdyenQueue->pop() );
	}

	/**
	 * With reject-notifications-without-hmac off, failures are only logged
	 * and the notification is processed as before.
	 *
	 * @dataProvider jobQueueingNotificationProvider
	 */
	public function testInvalidSignatureAcceptedWhenNotRejecting( array $webhook ): void {
		$this->config->override( [ 'reject-notifications-without-hmac' => false ] );
		$output = $this->sendToListener(
			$this->signWebhook( $webhook, self::INVALID_HMAC_KEY ), $this->getResponseExpectingSuccess()
		);
		$this->assertStringContainsString( '[accepted]', $output );
		$this->assertNotNull( $this->jobsAdyenQueue->pop() );
	}

	public function testAuthorisationMessageReceivedAndAcknowledged() {
		$request = $this->getMockBuilder( Request::class )->disableOriginalConstructor()
		->getMock();
		$response = $this->getMockBuilder( Response::class )->disableOriginalConstructor()
		->getMock();
		$request->method( 'getRawRequest' )
		->willReturn( $this->getSignedFixture( 'ipn_Authorisation_Non_Recurring.json' ) );
		$obj = json_decode( file_get_contents( __DIR__ . '/../Data/ipn_Authorisation_Non_Recurring.json' ), true );
		$authorisation = $obj['notificationItems'][0]['NotificationRequestItem'];
		ob_start();
		$this->rest_listener->execute( $request, $response );
		$getContent = ob_get_contents();
		ob_end_clean();
		$message = $this->jobsAdyenQueue->pop();
		$this->assertNotNull( $message );
		$this->assertEquals( $authorisation['pspReference'], $message['payload']['gatewayTxnId'] );
		$this->assertEquals( $authorisation['merchantReference'], $message['payload']['merchantReference'] );
		$this->assertEquals( $authorisation['eventDate'], $message['payload']['eventDate'] );
		$this->assertEquals( $authorisation['amount']['currency'], $message['payload']['currency'] );
		$this->assertEquals( $authorisation['amount']['value'] / 100, $message['payload']['amount'] );
		$this->assertStringContainsString( "[accepted]", $getContent );
	}

	public function testAuthorisationRecurringMessageReceivedAndAcknowledged() {
		$request = $this->getMockBuilder( Request::class )->disableOriginalConstructor()
		->getMock();
		$response = $this->getMockBuilder( Response::class )->disableOriginalConstructor()
		->getMock();
		$request->method( 'getRawRequest' )
		->willReturn( $this->getSignedFixture( 'ipn_Authorisation.json' ) );
		ob_start();
		$this->rest_listener->execute( $request, $response );
		$getContent = ob_get_contents();
		ob_end_clean();
		$message = $this->jobsAdyenQueue->pop();
		$this->assertNull( $message );
		$this->assertStringContainsString( "[accepted]", $getContent );
	}

	public function testAutoRescueMessageReceivedAndAcknowledged() {
		$request = $this->getMockBuilder( Request::class )->disableOriginalConstructor()
		->getMock();
		$response = $this->getMockBuilder( Response::class )->disableOriginalConstructor()
		->getMock();
		$request->method( 'getRawRequest' )
		->willReturn( $this->getSignedFixture( 'ipn_Autorescue.json' ) );
		ob_start();
		$this->rest_listener->execute( $request, $response );
		$getContent = ob_get_contents();
		ob_end_clean();
		$message = $this->jobsAdyenQueue->pop();
		$this->assertNull( $message );
		$this->assertStringContainsString( "[accepted]", $getContent );
	}

	public function testCancelAutoRescueMessageReceivedAndAcknowledged() {
		$request = $this->getMockBuilder( Request::class )->disableOriginalConstructor()
		->getMock();
		$response = $this->getMockBuilder( Response::class )->disableOriginalConstructor()
		->getMock();
		$request->method( 'getRawRequest' )
		->willReturn( $this->getSignedFixture( 'ipn_CancelAutorescue.json' ) );

		ob_start();
		$this->rest_listener->execute( $request, $response );
		$getContent = ob_get_contents();
		ob_end_clean();
		$message = $this->jobsAdyenQueue->pop();
		$this->assertNull( $message );
		$this->assertStringContainsString( "[accepted]", $getContent );
	}

	public function testCancellationMessageReceivedAndAcknowledged() {
		$request = $this->getMockBuilder( Request::class )->disableOriginalConstructor()
		->getMock();
		$response = $this->getMockBuilder( Response::class )->disableOriginalConstructor()
		->getMock();
		$request->method( 'getRawRequest' )
		->willReturn( $this->getSignedFixture( 'ipn_Cancellation.json' ) );

		ob_start();
		$this->rest_listener->execute( $request, $response );
		$getContent = ob_get_contents();
		ob_end_clean();
		$message = $this->jobsAdyenQueue->pop();
		$this->assertNull( $message );
		$this->assertStringContainsString( "[accepted]", $getContent );
	}

	public function testCaptureMessageReceivedAndAcknowledged() {
		$request = $this->getMockBuilder( Request::class )->disableOriginalConstructor()
		->getMock();
		$response = $this->getMockBuilder( Response::class )->disableOriginalConstructor()
		->getMock();
		$obj = json_decode( file_get_contents( __DIR__ . '/../Data/ipn_Capture.json' ), true );
		$capture = $obj['notificationItems'][0]['NotificationRequestItem'];
		$request->method( 'getRawRequest' )
		->willReturn( $this->getSignedFixture( 'ipn_Capture.json' ) );

		ob_start();
		$this->rest_listener->execute( $request, $response );
		$getContent = ob_get_contents();
		ob_end_clean();
		$message = $this->jobsAdyenQueue->pop();
		$this->assertNotNull( $message );
		$this->assertEquals( $capture['originalReference'], $message['payload']['gatewayTxnId'] );
		$this->assertEquals( $capture['merchantReference'], $message['payload']['merchantReference'] );
		$this->assertEquals( $capture['amount']['currency'], $message['payload']['currency'] );
		$this->assertEquals( $capture['amount']['value'] / 100, $message['payload']['amount'] );
		$this->assertStringContainsString( "[accepted]", $getContent );
	}

	public function testGravyCaptureMessageAcceptedAndDropped(): void {
		// Splice a colon-suffixed merchantReference into the Gravy capture fixture
		// to reproduce the message Adyen sends for Gravy-orchestrated captures.
		$webhook = json_decode( file_get_contents( __DIR__ . '/../Data/ipn_Capture_Gravy.json' ), true );

		$webhook['notificationItems'][0]['NotificationRequestItem']['merchantReference'] =
			'61SCO9jPrV41AIP8Wl5xkm:4Fn2bJ6cThbEiEdI1zGkYi';

		$request = $this->getMockBuilder( Request::class )->disableOriginalConstructor()
			->getMock();
		$response = $this->getMockBuilder( Response::class )->disableOriginalConstructor()
			->getMock();
		$request->method( 'getRawRequest' )
			->willReturn( json_encode( $this->signWebhook( $webhook ) ) );
		// RestListener::execute only calls setStatusCode from its catch blocks,
		// so this asserts no exception bubbled up from message construction -
		// i.e. Base62Helper::toUuid was not called on the colon-suffixed reference.
		$response->expects( $this->never() )->method( 'setStatusCode' );

		ob_start();
		$this->rest_listener->execute( $request, $response );
		$getContent = ob_get_contents();
		ob_end_clean();
		// Gravy-tagged captures are dropped by CaptureResponseAction, so the
		// record-capture job queue stays empty and the IPN is acknowledged.
		$message = $this->jobsAdyenQueue->pop();
		$this->assertNull( $message );
		$this->assertStringContainsString( "[accepted]", $getContent );
	}

	public function testCaptureFailedMessageReceivedAndAcknowledged() {
		$request = $this->getMockBuilder( Request::class )->disableOriginalConstructor()
		->getMock();
		$response = $this->getMockBuilder( Response::class )->disableOriginalConstructor()
		->getMock();
		$request->method( 'getRawRequest' )
		->willReturn( $this->getSignedFixture( 'ipn_CaptureFailed.json' ) );

		ob_start();
		$this->rest_listener->execute( $request, $response );
		$getContent = ob_get_contents();
		ob_end_clean();
		$message = $this->jobsAdyenQueue->pop();
		$this->assertNull( $message );
		$this->assertStringContainsString( "[accepted]", $getContent );
	}

	public function testGravyCaptureFailedMessageAcceptedAndDropped(): void {
		// Splice a colon-separated merchantReference into the CaptureFailed
		// fixture to reproduce the message Adyen sends for Gravy-orchestrated
		// capture failures.
		$webhook = json_decode( file_get_contents( __DIR__ . '/../Data/ipn_CaptureFailed.json' ), true );

		$webhook['notificationItems'][0]['NotificationRequestItem']['merchantReference'] =
			'6uZ3kxuUjdKWTQ9GD9sUQF:7bVaCLc5lHvTDfrLKbJtkf';

		$request = $this->getMockBuilder( Request::class )->disableOriginalConstructor()
			->getMock();
		$response = $this->getMockBuilder( Response::class )->disableOriginalConstructor()
			->getMock();
		$request->method( 'getRawRequest' )
			->willReturn( json_encode( $this->signWebhook( $webhook ) ) );
		// RestListener::execute only calls setStatusCode from its catch blocks,
		// so this asserts no exception bubbled up from message construction -
		// i.e. Base62Helper::toUuid was not called on the colon-separated reference.
		$response->expects( $this->never() )->method( 'setStatusCode' );

		ob_start();
		$this->rest_listener->execute( $request, $response );
		$getContent = ob_get_contents();
		ob_end_clean();
		$message = $this->jobsAdyenQueue->pop();
		$this->assertNull( $message );
		$this->assertStringContainsString( "[accepted]", $getContent );
	}

	public function testChargeBackMessageReceivedAndAcknowledged() {
		$request = $this->getMockBuilder( Request::class )->disableOriginalConstructor()
		->getMock();
		$response = $this->getMockBuilder( Response::class )->disableOriginalConstructor()
		->getMock();
		$request->method( 'getRawRequest' )
		->willReturn( $this->getSignedFixture( 'ipn_Chargeback.json' ) );
		$obj = json_decode( file_get_contents( __DIR__ . '/../Data/ipn_Chargeback.json' ), true );
		$chargeback = $obj['notificationItems'][0]['NotificationRequestItem'];
		ob_start();
		$this->rest_listener->execute( $request, $response );
		$getContent = ob_get_contents();
		ob_end_clean();
		$message = $this->refundQueue->pop();
		$this->assertEquals( $chargeback['pspReference'], $message['gateway_refund_id'] );
		$this->assertEquals( $chargeback['originalReference'], $message['gateway_parent_id'] );
		$this->assertEquals( $chargeback['amount']['currency'], $message['gross_currency'] );
		$this->assertEquals( $chargeback['amount']['value'] / 100, $message['gross'] );
		$this->assertEquals( 'adyen', $message['gateway'] );
		$this->assertEquals( 'chargeback', $message['type'] );
		$this->assertStringContainsString( "[accepted]", $getContent );
	}

	public function testGravyChargeBackMessageReceivedAndAcknowledged() {
		$request = $this->getMockBuilder( Request::class )->disableOriginalConstructor()
			->getMock();
		$response = $this->getMockBuilder( Response::class )->disableOriginalConstructor()
			->getMock();
		$request->method( 'getRawRequest' )
			->willReturn( $this->getSignedFixture( 'ipn_Chargeback_Gravy.json' ) );
		$obj = json_decode( file_get_contents( __DIR__ . '/../Data/ipn_Chargeback_Gravy.json' ), true );
		$chargeback = $obj['notificationItems'][0]['NotificationRequestItem'];
		ob_start();
		$this->rest_listener->execute( $request, $response );
		$getContent = ob_get_contents();
		ob_end_clean();
		$message = $this->refundQueue->pop();
		$this->assertEquals(
			Base62Helper::toUuid( $chargeback['merchantReference'] ),
			$message['gateway_parent_id']
		);
		$this->assertEquals( $chargeback['pspReference'], $message['backend_processor_refund_id'] );
		$this->assertEquals( $chargeback['originalReference'], $message['backend_processor_parent_id'] );
		$this->assertEquals( $chargeback['amount']['currency'], $message['gross_currency'] );
		$this->assertEquals( $chargeback['amount']['value'] / 100, $message['gross'] );
		$this->assertEquals( 'adyen', $message['backend_processor'] );
		$this->assertEquals( 'gravy', $message['gateway'] );
		$this->assertEquals( 'chargeback', $message['type'] );
		$this->assertStringContainsString( "[accepted]", $getContent );
	}

	public function testChargeBackReversedMessageReceivedAndAcknowledged() {
		$request = $this->getMockBuilder( Request::class )->disableOriginalConstructor()
		->getMock();
		$response = $this->getMockBuilder( Response::class )->disableOriginalConstructor()
		->getMock();
		$request->method( 'getRawRequest' )
		->willReturn( $this->getSignedFixture( 'ipn_ChargebackReversed.json' ) );
		ob_start();
		$this->rest_listener->execute( $request, $response );
		$getContent = ob_get_contents();
		ob_end_clean();
		$message = $this->refundQueue->pop();
		$this->assertNull( $message );
		$this->assertStringContainsString( "[accepted]", $getContent );
	}

	public function testNotificationOfChargebackMessageReceivedAndAcknowledged() {
		$request = $this->getMockBuilder( Request::class )->disableOriginalConstructor()
		->getMock();
		$response = $this->getMockBuilder( Response::class )->disableOriginalConstructor()
		->getMock();
		$request->method( 'getRawRequest' )
		->willReturn( $this->getSignedFixture( 'ipn_NotificationOfChargeback.json' ) );
		ob_start();
		$this->rest_listener->execute( $request, $response );
		$getContent = ob_get_contents();
		ob_end_clean();
		$refundMessage = $this->refundQueue->pop();
		$message = $this->jobsAdyenQueue->pop();
		$this->assertNull( $message );
		$this->assertNull( $refundMessage );
		$this->assertStringContainsString( "[accepted]", $getContent );
	}

	public function testNotificationOfFraudMessageReceivedAndAcknowledged() {
		$request = $this->getMockBuilder( Request::class )->disableOriginalConstructor()
		->getMock();
		$response = $this->getMockBuilder( Response::class )->disableOriginalConstructor()
		->getMock();
		$request->method( 'getRawRequest' )
		->willReturn( $this->getSignedFixture( 'ipn_NotificationOfFraud.json' ) );
		ob_start();
		$this->rest_listener->execute( $request, $response );
		$getContent = ob_get_contents();
		ob_end_clean();
		$refundMessage = $this->refundQueue->pop();
		$message = $this->jobsAdyenQueue->pop();
		$this->assertNull( $message );
		$this->assertNull( $refundMessage );
		$this->assertStringContainsString( "[accepted]", $getContent );
	}

	public function testOrderClosedMessageReceivedAndAcknowledged() {
		$request = $this->getMockBuilder( Request::class )->disableOriginalConstructor()
		->getMock();
		$response = $this->getMockBuilder( Response::class )->disableOriginalConstructor()
		->getMock();
		$request->method( 'getRawRequest' )
		->willReturn( $this->getSignedFixture( 'ipn_OrderClosed.json' ) );
		ob_start();
		$this->rest_listener->execute( $request, $response );
		$getContent = ob_get_contents();
		ob_end_clean();
		$refundMessage = $this->refundQueue->pop();
		$message = $this->jobsAdyenQueue->pop();
		$this->assertNull( $message );
		$this->assertNull( $refundMessage );
		$this->assertStringContainsString( "[accepted]", $getContent );
	}

	public function testOrderOpenedMessageReceivedAndAcknowledged() {
		$request = $this->getMockBuilder( Request::class )->disableOriginalConstructor()
		->getMock();
		$response = $this->getMockBuilder( Response::class )->disableOriginalConstructor()
		->getMock();
		$request->method( 'getRawRequest' )
		->willReturn( $this->getSignedFixture( 'ipn_OrderOpened.json' ) );
		ob_start();
		$this->rest_listener->execute( $request, $response );
		$getContent = ob_get_contents();
		ob_end_clean();
		$refundMessage = $this->refundQueue->pop();
		$message = $this->jobsAdyenQueue->pop();
		$this->assertNull( $message );
		$this->assertNull( $refundMessage );
		$this->assertStringContainsString( "[accepted]", $getContent );
	}

	public function testtPreabitrationLostMessageReceivedAndAcknowledged() {
		$request = $this->getMockBuilder( Request::class )->disableOriginalConstructor()
		->getMock();
		$response = $this->getMockBuilder( Response::class )->disableOriginalConstructor()
		->getMock();
		$request->method( 'getRawRequest' )
		->willReturn( $this->getSignedFixture( 'ipn_PrearbitrationLost.json' ) );
		ob_start();
		$this->rest_listener->execute( $request, $response );
		$getContent = ob_get_contents();
		ob_end_clean();
		$refundMessage = $this->refundQueue->pop();
		$message = $this->jobsAdyenQueue->pop();
		$this->assertNull( $message );
		$this->assertNull( $refundMessage );
		$this->assertStringContainsString( "[accepted]", $getContent );
	}

	public function testPreabitrationWonMessageReceivedAndAcknowledged() {
		$request = $this->getMockBuilder( Request::class )->disableOriginalConstructor()
		->getMock();
		$response = $this->getMockBuilder( Response::class )->disableOriginalConstructor()
		->getMock();
		$request->method( 'getRawRequest' )
		->willReturn( $this->getSignedFixture( 'ipn_PrearbitrationWon.json' ) );
		ob_start();
		$this->rest_listener->execute( $request, $response );
		$getContent = ob_get_contents();
		ob_end_clean();
		$refundMessage = $this->refundQueue->pop();
		$message = $this->jobsAdyenQueue->pop();
		$this->assertNull( $message );
		$this->assertNull( $refundMessage );
		$this->assertStringContainsString( "[accepted]", $getContent );
	}

	public function testRecurringContractMessageReceivedAndAcknowledged() {
		$request = $this->getMockBuilder( Request::class )->disableOriginalConstructor()
		->getMock();
		$response = $this->getMockBuilder( Response::class )->disableOriginalConstructor()
		->getMock();
		$request->method( 'getRawRequest' )
		->willReturn( $this->getSignedFixture( 'ipn_RecurringContract.json' ) );
		$obj = json_decode( file_get_contents( __DIR__ . '/../Data/ipn_RecurringContract.json' ), true );
		$recurringContract = $obj['notificationItems'][0]['NotificationRequestItem'];
		ob_start();
		$this->rest_listener->execute( $request, $response );
		$getContent = ob_get_contents();
		ob_end_clean();
		$message = $this->jobsAdyenQueue->pop();
		$this->assertEquals( $recurringContract['originalReference'], $message['payload']['gatewayTxnId'] );
		$this->assertEquals( $recurringContract['merchantReference'], $message['payload']['merchantReference'] );
		$this->assertEquals( $recurringContract['eventDate'], $message['payload']['eventDate'] );
		$this->assertEquals( $recurringContract['pspReference'], $message['payload']['recurringPaymentToken'] );
		$this->assertEquals( $recurringContract['merchantReference'], $message['payload']['processorContactId'] );
		$this->assertEquals( $recurringContract['paymentMethod'], $message['payload']['paymentMethod'] );
		$this->assertStringContainsString( "[accepted]", $getContent );
	}

	public function testRefundMessageReceivedAndAcknowledged() {
		$request = $this->getMockBuilder( Request::class )->disableOriginalConstructor()
		->getMock();
		$response = $this->getMockBuilder( Response::class )->disableOriginalConstructor()
		->getMock();
		$request->method( 'getRawRequest' )
		->willReturn( $this->getSignedFixture( 'ipn_Refund.json' ) );
		$obj = json_decode( file_get_contents( __DIR__ . '/../Data/ipn_Refund.json' ), true );
		$chargeback = $obj['notificationItems'][0]['NotificationRequestItem'];
		ob_start();
		$this->rest_listener->execute( $request, $response );
		$getContent = ob_get_contents();
		ob_end_clean();
		$message = $this->refundQueue->pop();
		$this->assertEquals( $chargeback['pspReference'], $message['gateway_refund_id'] );
		$this->assertEquals( $chargeback['originalReference'], $message['gateway_parent_id'] );
		$this->assertEquals( $chargeback['amount']['currency'], $message['gross_currency'] );
		$this->assertEquals( $chargeback['amount']['value'] / 100, $message['gross'] );
		$this->assertEquals( 'adyen', $message['gateway'] );
		$this->assertEquals( 'refund', $message['type'] );
		$this->assertStringContainsString( "[accepted]", $getContent );
	}

	public function testRefundedReversedMessageReceivedAndAcknowledged() {
		$request = $this->getMockBuilder( Request::class )->disableOriginalConstructor()
		->getMock();
		$response = $this->getMockBuilder( Response::class )->disableOriginalConstructor()
		->getMock();
		$request->method( 'getRawRequest' )
		->willReturn( $this->getSignedFixture( 'ipn_RefundedReversed.json' ) );
		ob_start();
		$this->rest_listener->execute( $request, $response );
		$getContent = ob_get_contents();
		ob_end_clean();
		$refundMessage = $this->refundQueue->pop();
		$message = $this->jobsAdyenQueue->pop();
		$this->assertNull( $message );
		$this->assertNull( $refundMessage );
		$this->assertStringContainsString( "[accepted]", $getContent );
	}

	public function testReportAvailableMessageReceivedAndAcknowledged() {
		$request = $this->getMockBuilder( Request::class )->disableOriginalConstructor()
		->getMock();
		$response = $this->getMockBuilder( Response::class )->disableOriginalConstructor()
		->getMock();
		$request->method( 'getRawRequest' )
		->willReturn( $this->getSignedFixture( 'ipn_ReportAvailable.json' ) );
		$obj = json_decode( file_get_contents( __DIR__ . '/../Data/ipn_ReportAvailable.json' ), true );
		$reportMessage = $obj['notificationItems'][0]['NotificationRequestItem'];
		ob_start();
		$this->rest_listener->execute( $request, $response );
		$getContent = ob_get_contents();
		ob_end_clean();
		$message = $this->jobsAdyenQueue->pop();
		$this->assertNotNull( $message );
		$this->assertEquals( $reportMessage['merchantAccountCode'], $message['payload']['account'] );
		$this->assertEquals( $reportMessage['reason'], $message['payload']['reportUrl'] );
		$this->assertStringContainsString( "[accepted]", $getContent );
	}

	public function testRequestForInformationMessageReceivedAndAcknowledged() {
		$request = $this->getMockBuilder( Request::class )->disableOriginalConstructor()
		->getMock();
		$response = $this->getMockBuilder( Response::class )->disableOriginalConstructor()
		->getMock();
		$request->method( 'getRawRequest' )
		->willReturn( $this->getSignedFixture( 'ipn_RequestForInformation.json' ) );
		ob_start();
		$this->rest_listener->execute( $request, $response );
		$getContent = ob_get_contents();
		ob_end_clean();
		$message = $this->jobsAdyenQueue->pop();
		$this->assertNull( $message );
		$this->assertStringContainsString( "[accepted]", $getContent );
	}

	public function testSecondChargebackMessageReceivedAndAcknowledged() {
		$request = $this->getMockBuilder( Request::class )->disableOriginalConstructor()
		->getMock();
		$response = $this->getMockBuilder( Response::class )->disableOriginalConstructor()
		->getMock();
		$request->method( 'getRawRequest' )
		->willReturn( $this->getSignedFixture( 'ipn_SecondChargeback.json' ) );
		$obj = json_decode( file_get_contents( __DIR__ . '/../Data/ipn_SecondChargeback.json' ), true );
		$chargeback = $obj['notificationItems'][0]['NotificationRequestItem'];
		ob_start();
		$this->rest_listener->execute( $request, $response );
		$getContent = ob_get_contents();
		ob_end_clean();
		$message = $this->refundQueue->pop();
		$this->assertNotNull( $message );
		$this->assertEquals( $chargeback['pspReference'], $message['gateway_refund_id'] );
		$this->assertEquals( $chargeback['pspReference'], $message['gateway_parent_id'] );
		$this->assertEquals( $chargeback['amount']['currency'], $message['gross_currency'] );
		$this->assertEquals( $chargeback['amount']['value'] / 100, $message['gross'] );
		$this->assertEquals( 'adyen', $message['gateway'] );
		$this->assertEquals( 'chargeback', $message['type'] );
		$this->assertStringContainsString( "[accepted]", $getContent );
	}
}
