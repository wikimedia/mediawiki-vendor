<?php

namespace SmashPig\PaymentProviders\Adyen\Tests\phpunit;

use SmashPig\Core\Context;
use SmashPig\Core\Http\Request;
use SmashPig\PaymentProviders\Adyen\AdyenRestListener;
use SmashPig\PaymentProviders\Adyen\Tests\BaseAdyenTestCase;
use Symfony\Component\HttpFoundation\Response;

/**
 * @group Adyen
 */
class NotificationRequestTest extends BaseAdyenTestCase {

	private $jobsAdyenQueue;

	private $refundQueue;

	/**
	 * @var AdyenRestListener
	 */
	public $rest_listener;

	public function setUp() : void {
		parent::setUp();
		$this->jobsAdyenQueue = Context::get()->getGlobalConfiguration()
			->object( 'data-store/jobs-adyen' );

		$this->refundQueue = Context::get()->getGlobalConfiguration()
			->object( 'data-store/refund' );

		$this->rest_listener = $this->config->object( 'endpoints/listener' );
	}

	public function testJSONAuthorisationMessageReceivedAndAcknowledged() {
		$request = $this->getMockBuilder( Request::class )->disableOriginalConstructor()
		->getMock();
		$response = $this->getMockBuilder( Response::class )->disableOriginalConstructor()
		->getMock();
		$request->method( 'getRawRequest' )
		->willReturn( file_get_contents( __DIR__ . '/../Data/ipn_Authorisation_Non_Recurring.json' ) );
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

	public function testJSONAuthorisationRecurringMessageReceivedAndAcknowledged() {
		$request = $this->getMockBuilder( Request::class )->disableOriginalConstructor()
		->getMock();
		$response = $this->getMockBuilder( Response::class )->disableOriginalConstructor()
		->getMock();
		$request->method( 'getRawRequest' )
		->willReturn( file_get_contents( __DIR__ . '/../Data/ipn_Authorisation.json' ) );
		ob_start();
		$this->rest_listener->execute( $request, $response );
		$getContent = ob_get_contents();
		ob_end_clean();
		$message = $this->jobsAdyenQueue->pop();
		$this->assertNull( $message );
		$this->assertStringContainsString( "[accepted]", $getContent );
	}

	public function testAutoRescueJSONMessageReceivedAndAcknowledged() {
		$request = $this->getMockBuilder( Request::class )->disableOriginalConstructor()
		->getMock();
		$response = $this->getMockBuilder( Response::class )->disableOriginalConstructor()
		->getMock();
		$request->method( 'getRawRequest' )
		->willReturn( file_get_contents( __DIR__ . '/../Data/ipn_Autorescue.json' ) );
		ob_start();
		$this->rest_listener->execute( $request, $response );
		$getContent = ob_get_contents();
		ob_end_clean();
		$message = $this->jobsAdyenQueue->pop();
		$this->assertNull( $message );
		$this->assertStringContainsString( "[accepted]", $getContent );
	}

	public function testCancelAutoRescueJSONMessageReceivedAndAcknowledged() {
		$request = $this->getMockBuilder( Request::class )->disableOriginalConstructor()
		->getMock();
		$response = $this->getMockBuilder( Response::class )->disableOriginalConstructor()
		->getMock();
		$request->method( 'getRawRequest' )
		->willReturn( file_get_contents( __DIR__ . '/../Data/ipn_CancelAutorescue.json' ) );

		ob_start();
		$this->rest_listener->execute( $request, $response );
		$getContent = ob_get_contents();
		ob_end_clean();
		$message = $this->jobsAdyenQueue->pop();
		$this->assertNull( $message );
		$this->assertStringContainsString( "[accepted]", $getContent );
	}

	public function testCancellationJSONMessageReceivedAndAcknowledged() {
		$request = $this->getMockBuilder( Request::class )->disableOriginalConstructor()
		->getMock();
		$response = $this->getMockBuilder( Response::class )->disableOriginalConstructor()
		->getMock();
		$request->method( 'getRawRequest' )
		->willReturn( file_get_contents( __DIR__ . '/../Data/ipn_Cancellation.json' ) );

		ob_start();
		$this->rest_listener->execute( $request, $response );
		$getContent = ob_get_contents();
		ob_end_clean();
		$message = $this->jobsAdyenQueue->pop();
		$this->assertNull( $message );
		$this->assertStringContainsString( "[accepted]", $getContent );
	}

	public function testCaptureJSONMessageReceivedAndAcknowledged() {
		$request = $this->getMockBuilder( Request::class )->disableOriginalConstructor()
		->getMock();
		$response = $this->getMockBuilder( Response::class )->disableOriginalConstructor()
		->getMock();
		$obj = json_decode( file_get_contents( __DIR__ . '/../Data/ipn_Capture.json' ), true );
		$capture = $obj['notificationItems'][0]['NotificationRequestItem'];
		$request->method( 'getRawRequest' )
		->willReturn( file_get_contents( __DIR__ . '/../Data/ipn_Capture.json' ) );

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

	public function testCaptureFailedJSONMessageReceivedAndAcknowledged() {
		$request = $this->getMockBuilder( Request::class )->disableOriginalConstructor()
		->getMock();
		$response = $this->getMockBuilder( Response::class )->disableOriginalConstructor()
		->getMock();
		$request->method( 'getRawRequest' )
		->willReturn( file_get_contents( __DIR__ . '/../Data/ipn_CaptureFailed.json' ) );

		ob_start();
		$this->rest_listener->execute( $request, $response );
		$getContent = ob_get_contents();
		ob_end_clean();
		$message = $this->jobsAdyenQueue->pop();
		$this->assertNull( $message );
		$this->assertStringContainsString( "[accepted]", $getContent );
	}

	public function testChargeBackJSONMessageReceivedAndAcknowledged() {
		$request = $this->getMockBuilder( Request::class )->disableOriginalConstructor()
		->getMock();
		$response = $this->getMockBuilder( Response::class )->disableOriginalConstructor()
		->getMock();
		$request->method( 'getRawRequest' )
		->willReturn( file_get_contents( __DIR__ . '/../Data/ipn_Chargeback.json' ) );
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

	public function testChargeBackReversedJSONMessageReceivedAndAcknowledged() {
		$request = $this->getMockBuilder( Request::class )->disableOriginalConstructor()
		->getMock();
		$response = $this->getMockBuilder( Response::class )->disableOriginalConstructor()
		->getMock();
		$request->method( 'getRawRequest' )
		->willReturn( file_get_contents( __DIR__ . '/../Data/ipn_ChargebackReversed.json' ) );
		ob_start();
		$this->rest_listener->execute( $request, $response );
		$getContent = ob_get_contents();
		ob_end_clean();
		$message = $this->refundQueue->pop();
		$this->assertNull( $message );
		$this->assertStringContainsString( "[accepted]", $getContent );
	}

	public function testNotificationOfChargebackJSONMessageReceivedAndAcknowledged() {
		$request = $this->getMockBuilder( Request::class )->disableOriginalConstructor()
		->getMock();
		$response = $this->getMockBuilder( Response::class )->disableOriginalConstructor()
		->getMock();
		$request->method( 'getRawRequest' )
		->willReturn( file_get_contents( __DIR__ . '/../Data/ipn_NotificationOfChargeback.json' ) );
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

	public function testNotificationOfFraudJSONMessageReceivedAndAcknowledged() {
		$request = $this->getMockBuilder( Request::class )->disableOriginalConstructor()
		->getMock();
		$response = $this->getMockBuilder( Response::class )->disableOriginalConstructor()
		->getMock();
		$request->method( 'getRawRequest' )
		->willReturn( file_get_contents( __DIR__ . '/../Data/ipn_NotificationOfFraud.json' ) );
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

	public function testOrderClosedJSONMessageReceivedAndAcknowledged() {
		$request = $this->getMockBuilder( Request::class )->disableOriginalConstructor()
		->getMock();
		$response = $this->getMockBuilder( Response::class )->disableOriginalConstructor()
		->getMock();
		$request->method( 'getRawRequest' )
		->willReturn( file_get_contents( __DIR__ . '/../Data/ipn_OrderClosed.json' ) );
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

	public function testOrderOpenedJSONMessageReceivedAndAcknowledged() {
		$request = $this->getMockBuilder( Request::class )->disableOriginalConstructor()
		->getMock();
		$response = $this->getMockBuilder( Response::class )->disableOriginalConstructor()
		->getMock();
		$request->method( 'getRawRequest' )
		->willReturn( file_get_contents( __DIR__ . '/../Data/ipn_OrderOpened.json' ) );
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

	public function testtPreabitrationLostJSONMessageReceivedAndAcknowledged() {
		$request = $this->getMockBuilder( Request::class )->disableOriginalConstructor()
		->getMock();
		$response = $this->getMockBuilder( Response::class )->disableOriginalConstructor()
		->getMock();
		$request->method( 'getRawRequest' )
		->willReturn( file_get_contents( __DIR__ . '/../Data/ipn_PrearbitrationLost.json' ) );
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

	public function testPreabitrationWonJSONMessageReceivedAndAcknowledged() {
		$request = $this->getMockBuilder( Request::class )->disableOriginalConstructor()
		->getMock();
		$response = $this->getMockBuilder( Response::class )->disableOriginalConstructor()
		->getMock();
		$request->method( 'getRawRequest' )
		->willReturn( file_get_contents( __DIR__ . '/../Data/ipn_PrearbitrationWon.json' ) );
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

	public function testRecurringContractJSONMessageReceivedAndAcknowledged() {
		$request = $this->getMockBuilder( Request::class )->disableOriginalConstructor()
		->getMock();
		$response = $this->getMockBuilder( Response::class )->disableOriginalConstructor()
		->getMock();
		$request->method( 'getRawRequest' )
		->willReturn( file_get_contents( __DIR__ . '/../Data/ipn_RecurringContract.json' ) );
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

	public function testRefundJSONMessageReceivedAndAcknowledged() {
		$request = $this->getMockBuilder( Request::class )->disableOriginalConstructor()
		->getMock();
		$response = $this->getMockBuilder( Response::class )->disableOriginalConstructor()
		->getMock();
		$request->method( 'getRawRequest' )
		->willReturn( file_get_contents( __DIR__ . '/../Data/ipn_Refund.json' ) );
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

	public function testRefundedReversedJSONMessageReceivedAndAcknowledged() {
		$request = $this->getMockBuilder( Request::class )->disableOriginalConstructor()
		->getMock();
		$response = $this->getMockBuilder( Response::class )->disableOriginalConstructor()
		->getMock();
		$request->method( 'getRawRequest' )
		->willReturn( file_get_contents( __DIR__ . '/../Data/ipn_RefundedReversed.json' ) );
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

	public function testReportAvailableJSONMessageReceivedAndAcknowledged() {
		$request = $this->getMockBuilder( Request::class )->disableOriginalConstructor()
		->getMock();
		$response = $this->getMockBuilder( Response::class )->disableOriginalConstructor()
		->getMock();
		$request->method( 'getRawRequest' )
		->willReturn( file_get_contents( __DIR__ . '/../Data/ipn_ReportAvailable.json' ) );
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

	public function testRequestForInformationJSONMessageReceivedAndAcknowledged() {
		$request = $this->getMockBuilder( Request::class )->disableOriginalConstructor()
		->getMock();
		$response = $this->getMockBuilder( Response::class )->disableOriginalConstructor()
		->getMock();
		$request->method( 'getRawRequest' )
		->willReturn( file_get_contents( __DIR__ . '/../Data/ipn_RequestForInformation.json' ) );
		ob_start();
		$this->rest_listener->execute( $request, $response );
		$getContent = ob_get_contents();
		ob_end_clean();
		$message = $this->jobsAdyenQueue->pop();
		$this->assertNull( $message );
		$this->assertStringContainsString( "[accepted]", $getContent );
	}

	public function testSecondChargebackJSONMessageReceivedAndAcknowledged() {
		$request = $this->getMockBuilder( Request::class )->disableOriginalConstructor()
		->getMock();
		$response = $this->getMockBuilder( Response::class )->disableOriginalConstructor()
		->getMock();
		$request->method( 'getRawRequest' )
		->willReturn( file_get_contents( __DIR__ . '/../Data/ipn_SecondChargeback.json' ) );
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
