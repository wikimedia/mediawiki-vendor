<?php

namespace SmashPig\PaymentProviders\Adyen\Tests\phpunit;

use SmashPig\Core\Context;
use SmashPig\Core\Http\Request;
use SmashPig\Core\Http\Response;
use SmashPig\PaymentProviders\Adyen\AdyenListener;
use SmashPig\PaymentProviders\Adyen\Tests\BaseAdyenTestCase;

/**
 * @group Adyen
 */
class NotificationRequestTest extends BaseAdyenTestCase {

	private $jobsAdyenQueue;

	private $refundQueue;

	/**
	 * @var AdyenListener
	 */
	public $soap_listener;

	/**
	 * @var AdyenListener
	 */
	public $rest_listener;

	public function setUp() : void {
		parent::setUp();
		$this->jobsAdyenQueue = Context::get()->getGlobalConfiguration()
			->object( 'data-store/jobs-adyen' );

		$this->refundQueue = Context::get()->getGlobalConfiguration()
			->object( 'data-store/refund' );
		if ( class_exists( \SoapServer::class ) ) {
			$this->soap_listener = $this->config->object( 'soap-listener' );
		}

		$this->rest_listener = $this->config->object( 'rest-listener' );
	}

	public function testSoapAuthorisationMessageReceivedAndAcknowledged() {
		if ( !class_exists( \SoapServer::class ) ) {
			$this->markTestSkipped( 'Soap server disabled on CI' );
		}
		$request = $this->getMockBuilder( Request::class )->disableOriginalConstructor()
		->getMock();
		$response = $this->getMockBuilder( Response::class )->disableOriginalConstructor()
		->getMock();
		$request->method( 'getRawRequest' )
		->willReturn( file_get_contents( __DIR__ . '/../Data/ipn_Authorisation_Non_Recurring.xml' ) );
		$obj = json_decode( file_get_contents( __DIR__ . '/../Data/ipn_Authorisation_Non_Recurring.json' ), true );
		$authorisation = $obj['notificationItems'][0]['NotificationRequestItem'];
		ob_start();
		$this->soap_listener->execute( $request, $response );
		$getContent = ob_get_contents();
		ob_end_clean();
		$message = $this->jobsAdyenQueue->pop();
		$this->assertNotNull( $message );
		$this->assertEquals( $authorisation['pspReference'], $message['gatewayTxnId'] );
		$this->assertEquals( $authorisation['merchantReference'], $message['merchantReference'] );
		$this->assertEquals( $authorisation['eventDate'], $message['eventDate'] );
		$this->assertEquals( $authorisation['amount']['currency'], $message['currency'] );
		$this->assertEquals( $authorisation['amount']['value'] / 100, $message['amount'] );
		$this->assertStringContainsString( "[accepted]", $getContent );
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
		$this->assertEquals( $authorisation['pspReference'], $message['gatewayTxnId'] );
		$this->assertEquals( $authorisation['merchantReference'], $message['merchantReference'] );
		$this->assertEquals( $authorisation['eventDate'], $message['eventDate'] );
		$this->assertEquals( $authorisation['amount']['currency'], $message['currency'] );
		$this->assertEquals( $authorisation['amount']['value'] / 100, $message['amount'] );
		$this->assertStringContainsString( "[accepted]", $getContent );
	}

	public function testSOAPAuthorisationRecurringMessageReceivedAndAcknowledged() {
		if ( !class_exists( \SoapServer::class ) ) {
			$this->markTestSkipped( 'Soap server disabled on CI' );
		}
		$request = $this->getMockBuilder( Request::class )->disableOriginalConstructor()
		->getMock();
		$response = $this->getMockBuilder( Response::class )->disableOriginalConstructor()
		->getMock();
		$request->method( 'getRawRequest' )
		->willReturn( file_get_contents( __DIR__ . '/../Data/ipn_Authorisation.xml' ) );
		ob_start();
		$this->soap_listener->execute( $request, $response );
		$getContent = ob_get_contents();
		ob_end_clean();
		$message = $this->jobsAdyenQueue->pop();
		$this->assertNull( $message );
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

	public function testAutoRescueSoapMessageReceivedAndAcknowledged() {
		if ( !class_exists( \SoapServer::class ) ) {
			$this->markTestSkipped( 'Soap server disabled on CI' );
		}
		$request = $this->getMockBuilder( Request::class )->disableOriginalConstructor()
		->getMock();
		$response = $this->getMockBuilder( Response::class )->disableOriginalConstructor()
		->getMock();
		$request->method( 'getRawRequest' )
		->willReturn( file_get_contents( __DIR__ . '/../Data/ipn_Autorescue.xml' ) );
		$obj = json_decode( file_get_contents( __DIR__ . '/../Data/ipn_Autorescue.json' ), true );
		$autorescue = $obj['notificationItems'][0]['NotificationRequestItem'];
		ob_start();
		$this->soap_listener->execute( $request, $response );
		$getContent = ob_get_contents();
		ob_end_clean();
		$message = $this->jobsAdyenQueue->pop();
		$this->assertNotNull( $message );
		$this->assertEquals( $autorescue['pspReference'], $message['pspReference'] );
		$this->assertEquals( $autorescue['merchantReference'], $message['merchantReference'] );
		$this->assertEquals( $autorescue['additionalData']['retry.rescueReference'], $message['retryRescueReference'] );
		$this->assertEquals( $autorescue['amount']['currency'], $message['currency'] );
		$this->assertEquals( $autorescue['amount']['value'] / 100, $message['amount'] );
		$this->assertTrue( $message['isSuccessfulAutoRescue'] );
		$this->assertFalse( $message['isEndedAutoRescue'] );
		$this->assertFalse( $message['processAutoRescueCapture'] );
		$this->assertStringContainsString( "[accepted]", $getContent );
	}

	public function testAutoRescueJSONMessageReceivedAndAcknowledged() {
		$request = $this->getMockBuilder( Request::class )->disableOriginalConstructor()
		->getMock();
		$response = $this->getMockBuilder( Response::class )->disableOriginalConstructor()
		->getMock();
		$request->method( 'getRawRequest' )
		->willReturn( file_get_contents( __DIR__ . '/../Data/ipn_Autorescue.json' ) );
		$obj = json_decode( file_get_contents( __DIR__ . '/../Data/ipn_Autorescue.json' ), true );
		$autorescue = $obj['notificationItems'][0]['NotificationRequestItem'];
		ob_start();
		$this->rest_listener->execute( $request, $response );
		$getContent = ob_get_contents();
		ob_end_clean();
		$message = $this->jobsAdyenQueue->pop();
		$this->assertNotNull( $message );
		$this->assertEquals( $autorescue['pspReference'], $message['pspReference'] );
		$this->assertEquals( $autorescue['merchantReference'], $message['merchantReference'] );
		$this->assertEquals( $autorescue['additionalData']['retry.rescueReference'], $message['retryRescueReference'] );
		$this->assertEquals( $autorescue['amount']['currency'], $message['currency'] );
		$this->assertEquals( $autorescue['amount']['value'] / 100, $message['amount'] );
		$this->assertTrue( $message['isSuccessfulAutoRescue'] );
		$this->assertFalse( $message['isEndedAutoRescue'] );
		$this->assertFalse( $message['processAutoRescueCapture'] );
		$this->assertStringContainsString( "[accepted]", $getContent );
	}

	public function testCancelAutoRescueSoapMessageReceivedAndAcknowledged() {
		if ( !class_exists( \SoapServer::class ) ) {
			$this->markTestSkipped( 'Soap server disabled on CI' );
		}
		$request = $this->getMockBuilder( Request::class )->disableOriginalConstructor()
		->getMock();
		$response = $this->getMockBuilder( Response::class )->disableOriginalConstructor()
		->getMock();
		$request->method( 'getRawRequest' )
		->willReturn( file_get_contents( __DIR__ . '/../Data/ipn_CancelAutorescue.xml' ) );

		ob_start();
		$this->soap_listener->execute( $request, $response );
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

	public function testCancellationMessageReceivedAndAcknowledged() {
		if ( !class_exists( \SoapServer::class ) ) {
			$this->markTestSkipped( 'Soap server disabled on CI' );
		}
		$request = $this->getMockBuilder( Request::class )->disableOriginalConstructor()
		->getMock();
		$response = $this->getMockBuilder( Response::class )->disableOriginalConstructor()
		->getMock();
		$request->method( 'getRawRequest' )
		->willReturn( file_get_contents( __DIR__ . '/../Data/ipn_Cancellation.xml' ) );

		ob_start();
		$this->soap_listener->execute( $request, $response );
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

	public function testCaptureSoapMessageReceivedAndAcknowledged() {
		if ( !class_exists( \SoapServer::class ) ) {
			$this->markTestSkipped( 'Soap server disabled on CI' );
		}
		$obj = json_decode( file_get_contents( __DIR__ . '/../Data/ipn_Capture.json' ), true );
		$capture = $obj['notificationItems'][0]['NotificationRequestItem'];
		$request = $this->getMockBuilder( Request::class )->disableOriginalConstructor()
		->getMock();
		$response = $this->getMockBuilder( Response::class )->disableOriginalConstructor()
		->getMock();
		$request->method( 'getRawRequest' )
		->willReturn( file_get_contents( __DIR__ . '/../Data/ipn_Capture.xml' ) );
		$obj = json_decode( file_get_contents( __DIR__ . '/../Data/ipn_Capture.json' ), true );
		$capture = $obj['notificationItems'][0]['NotificationRequestItem'];
		ob_start();
		$this->soap_listener->execute( $request, $response );
		$getContent = ob_get_contents();
		ob_end_clean();
		$message = $this->jobsAdyenQueue->pop();
		$this->assertNotNull( $message );
		$this->assertEquals( $capture['originalReference'], $message['gatewayTxnId'] );
		$this->assertEquals( $capture['merchantReference'], $message['merchantReference'] );
		$this->assertEquals( $capture['amount']['currency'], $message['currency'] );
		$this->assertEquals( $capture['amount']['value'] / 100, $message['amount'] );
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
		$this->assertEquals( $capture['originalReference'], $message['gatewayTxnId'] );
		$this->assertEquals( $capture['merchantReference'], $message['merchantReference'] );
		$this->assertEquals( $capture['amount']['currency'], $message['currency'] );
		$this->assertEquals( $capture['amount']['value'] / 100, $message['amount'] );
		$this->assertStringContainsString( "[accepted]", $getContent );
	}

	public function testCaptureFailedSOAPMessageReceivedAndAcknowledged() {
		if ( !class_exists( \SoapServer::class ) ) {
			$this->markTestSkipped( 'Soap server disabled on CI' );
		}
		$request = $this->getMockBuilder( Request::class )->disableOriginalConstructor()
		->getMock();
		$response = $this->getMockBuilder( Response::class )->disableOriginalConstructor()
		->getMock();
		$request->method( 'getRawRequest' )
		->willReturn( file_get_contents( __DIR__ . '/../Data/ipn_CaptureFailed.xml' ) );

		ob_start();
		$this->soap_listener->execute( $request, $response );
		$getContent = ob_get_contents();
		ob_end_clean();
		$message = $this->jobsAdyenQueue->pop();
		$this->assertNull( $message );
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

	public function testChargeBackSOAPMessageReceivedAndAcknowledged() {
		if ( !class_exists( \SoapServer::class ) ) {
			$this->markTestSkipped( 'Soap server disabled on CI' );
		}
		$request = $this->getMockBuilder( Request::class )->disableOriginalConstructor()
		->getMock();
		$response = $this->getMockBuilder( Response::class )->disableOriginalConstructor()
		->getMock();
		$request->method( 'getRawRequest' )
		->willReturn( file_get_contents( __DIR__ . '/../Data/ipn_Chargeback.xml' ) );
		$obj = json_decode( file_get_contents( __DIR__ . '/../Data/ipn_Chargeback.json' ), true );
		$chargeback = $obj['notificationItems'][0]['NotificationRequestItem'];
		ob_start();
		$this->soap_listener->execute( $request, $response );
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

	public function testChargeBackReversedSOAPMessageReceivedAndAcknowledged() {
		if ( !class_exists( \SoapServer::class ) ) {
			$this->markTestSkipped( 'Soap server disabled on CI' );
		}
		$request = $this->getMockBuilder( Request::class )->disableOriginalConstructor()
		->getMock();
		$response = $this->getMockBuilder( Response::class )->disableOriginalConstructor()
		->getMock();
		$request->method( 'getRawRequest' )
		->willReturn( file_get_contents( __DIR__ . '/../Data/ipn_ChargebackReversed.xml' ) );
		ob_start();
		$this->soap_listener->execute( $request, $response );
		$getContent = ob_get_contents();
		ob_end_clean();
		$message = $this->refundQueue->pop();
		$this->assertNull( $message );
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

	public function testNotificationOfChargebackSOAPMessageReceivedAndAcknowledged() {
		if ( !class_exists( \SoapServer::class ) ) {
			$this->markTestSkipped( 'Soap server disabled on CI' );
		}
		$request = $this->getMockBuilder( Request::class )->disableOriginalConstructor()
		->getMock();
		$response = $this->getMockBuilder( Response::class )->disableOriginalConstructor()
		->getMock();
		$request->method( 'getRawRequest' )
		->willReturn( file_get_contents( __DIR__ . '/../Data/ipn_NotificationOfChargeback.xml' ) );
		ob_start();
		$this->soap_listener->execute( $request, $response );
		$getContent = ob_get_contents();
		ob_end_clean();
		$refundMessage = $this->refundQueue->pop();
		$message = $this->jobsAdyenQueue->pop();
		$this->assertNull( $message );
		$this->assertNull( $refundMessage );
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

	public function testNotificationOfFraudSOAPMessageReceivedAndAcknowledged() {
		if ( !class_exists( \SoapServer::class ) ) {
			$this->markTestSkipped( 'Soap server disabled on CI' );
		}
		$request = $this->getMockBuilder( Request::class )->disableOriginalConstructor()
		->getMock();
		$response = $this->getMockBuilder( Response::class )->disableOriginalConstructor()
		->getMock();
		$request->method( 'getRawRequest' )
		->willReturn( file_get_contents( __DIR__ . '/../Data/ipn_NotificationOfFraud.xml' ) );
		ob_start();
		$this->soap_listener->execute( $request, $response );
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

	public function testOrderClosedSOAPMessageReceivedAndAcknowledged() {
		if ( !class_exists( \SoapServer::class ) ) {
			$this->markTestSkipped( 'Soap server disabled on CI' );
		}
		$request = $this->getMockBuilder( Request::class )->disableOriginalConstructor()
		->getMock();
		$response = $this->getMockBuilder( Response::class )->disableOriginalConstructor()
		->getMock();
		$request->method( 'getRawRequest' )
		->willReturn( file_get_contents( __DIR__ . '/../Data/ipn_OrderClosed.xml' ) );
		ob_start();
		$this->soap_listener->execute( $request, $response );
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

	public function testOrderOpenedSOAPMessageReceivedAndAcknowledged() {
		if ( !class_exists( \SoapServer::class ) ) {
			$this->markTestSkipped( 'Soap server disabled on CI' );
		}
		$request = $this->getMockBuilder( Request::class )->disableOriginalConstructor()
		->getMock();
		$response = $this->getMockBuilder( Response::class )->disableOriginalConstructor()
		->getMock();
		$request->method( 'getRawRequest' )
		->willReturn( file_get_contents( __DIR__ . '/../Data/ipn_OrderOpened.xml' ) );
		ob_start();
		$this->soap_listener->execute( $request, $response );
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

	public function testPreabitrationLostSOAPMessageReceivedAndAcknowledged() {
		if ( !class_exists( \SoapServer::class ) ) {
			$this->markTestSkipped( 'Soap server disabled on CI' );
		}
		$request = $this->getMockBuilder( Request::class )->disableOriginalConstructor()
		->getMock();
		$response = $this->getMockBuilder( Response::class )->disableOriginalConstructor()
		->getMock();
		$request->method( 'getRawRequest' )
		->willReturn( file_get_contents( __DIR__ . '/../Data/ipn_PrearbitrationLost.xml' ) );
		ob_start();
		$this->soap_listener->execute( $request, $response );
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

	public function testPreabitrationWonSOAPMessageReceivedAndAcknowledged() {
		if ( !class_exists( \SoapServer::class ) ) {
			$this->markTestSkipped( 'Soap server disabled on CI' );
		}
		$request = $this->getMockBuilder( Request::class )->disableOriginalConstructor()
		->getMock();
		$response = $this->getMockBuilder( Response::class )->disableOriginalConstructor()
		->getMock();
		$request->method( 'getRawRequest' )
		->willReturn( file_get_contents( __DIR__ . '/../Data/ipn_PrearbitrationWon.xml' ) );
		ob_start();
		$this->soap_listener->execute( $request, $response );
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

	public function testRecurringContractSOAPMessageReceivedAndAcknowledged() {
		if ( !class_exists( \SoapServer::class ) ) {
			$this->markTestSkipped( 'Soap server disabled on CI' );
		}
		$request = $this->getMockBuilder( Request::class )->disableOriginalConstructor()
		->getMock();
		$response = $this->getMockBuilder( Response::class )->disableOriginalConstructor()
		->getMock();
		$request->method( 'getRawRequest' )
		->willReturn( file_get_contents( __DIR__ . '/../Data/ipn_RecurringContract.xml' ) );
		$obj = json_decode( file_get_contents( __DIR__ . '/../Data/ipn_RecurringContract.json' ), true );
		$recurringContract = $obj['notificationItems'][0]['NotificationRequestItem'];

		ob_start();
		$this->soap_listener->execute( $request, $response );
		$getContent = ob_get_contents();
		ob_end_clean();
		$message = $this->jobsAdyenQueue->pop();
		$this->assertEquals( $recurringContract['originalReference'], $message['gatewayTxnId'] );
		$this->assertEquals( $recurringContract['merchantReference'], $message['merchantReference'] );
		$this->assertEquals( $recurringContract['eventDate'], $message['eventDate'] );
		$this->assertEquals( $recurringContract['pspReference'], $message['recurringPaymentToken'] );
		$this->assertEquals( $recurringContract['merchantReference'], $message['processorContactId'] );
		$this->assertEquals( $recurringContract['paymentMethod'], $message['paymentMethod'] );
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
		$this->assertEquals( $recurringContract['originalReference'], $message['gatewayTxnId'] );
		$this->assertEquals( $recurringContract['merchantReference'], $message['merchantReference'] );
		$this->assertEquals( $recurringContract['eventDate'], $message['eventDate'] );
		$this->assertEquals( $recurringContract['pspReference'], $message['recurringPaymentToken'] );
		$this->assertEquals( $recurringContract['merchantReference'], $message['processorContactId'] );
		$this->assertEquals( $recurringContract['paymentMethod'], $message['paymentMethod'] );
		$this->assertStringContainsString( "[accepted]", $getContent );
	}

	public function testRefundSOAPMessageReceivedAndAcknowledged() {
		if ( !class_exists( \SoapServer::class ) ) {
			$this->markTestSkipped( 'Soap server disabled on CI' );
		}
		$request = $this->getMockBuilder( Request::class )->disableOriginalConstructor()
		->getMock();
		$response = $this->getMockBuilder( Response::class )->disableOriginalConstructor()
		->getMock();
		$request->method( 'getRawRequest' )
		->willReturn( file_get_contents( __DIR__ . '/../Data/ipn_Refund.xml' ) );
		$obj = json_decode( file_get_contents( __DIR__ . '/../Data/ipn_Refund.json' ), true );
		$chargeback = $obj['notificationItems'][0]['NotificationRequestItem'];
		ob_start();
		$this->soap_listener->execute( $request, $response );
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

	public function testRefundedReversedSOAPMessageReceivedAndAcknowledged() {
		if ( !class_exists( \SoapServer::class ) ) {
			$this->markTestSkipped( 'Soap server disabled on CI' );
		}
		$request = $this->getMockBuilder( Request::class )->disableOriginalConstructor()
		->getMock();
		$response = $this->getMockBuilder( Response::class )->disableOriginalConstructor()
		->getMock();
		$request->method( 'getRawRequest' )
		->willReturn( file_get_contents( __DIR__ . '/../Data/ipn_RefundedReversed.xml' ) );
		ob_start();
		$this->soap_listener->execute( $request, $response );
		$getContent = ob_get_contents();
		ob_end_clean();
		$refundMessage = $this->refundQueue->pop();
		$message = $this->jobsAdyenQueue->pop();
		$this->assertNull( $message );
		$this->assertNull( $refundMessage );
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

	public function testReportAvailableSOAPMessageReceivedAndAcknowledged() {
		if ( !class_exists( \SoapServer::class ) ) {
			$this->markTestSkipped( 'Soap server disabled on CI' );
		}
		$request = $this->getMockBuilder( Request::class )->disableOriginalConstructor()
		->getMock();
		$response = $this->getMockBuilder( Response::class )->disableOriginalConstructor()
		->getMock();
		$request->method( 'getRawRequest' )
		->willReturn( file_get_contents( __DIR__ . '/../Data/ipn_ReportAvailable.xml' ) );
		$obj = json_decode( file_get_contents( __DIR__ . '/../Data/ipn_ReportAvailable.json' ), true );
		$reportMessage = $obj['notificationItems'][0]['NotificationRequestItem'];

		ob_start();
		$this->soap_listener->execute( $request, $response );
		$getContent = ob_get_contents();
		ob_end_clean();
		$message = $this->jobsAdyenQueue->pop();
		$this->assertNotNull( $message );
		$this->assertEquals( 'adyen', $message['gateway'] );
		$this->assertEquals( $reportMessage['merchantAccountCode'], $message['account'] );
		$this->assertEquals( $reportMessage['reason'], $message['reportUrl'] );
		$this->assertCount( 2, $message['propertiesExcludedFromExport'] );
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
		$this->assertEquals( 'adyen', $message['gateway'] );
		$this->assertEquals( $reportMessage['merchantAccountCode'], $message['account'] );
		$this->assertEquals( $reportMessage['reason'], $message['reportUrl'] );
		$this->assertCount( 2, $message['propertiesExcludedFromExport'] );
		$this->assertStringContainsString( "[accepted]", $getContent );
	}

	public function testRequestForInformationSOAPMessageReceivedAndAcknowledged() {
		if ( !class_exists( \SoapServer::class ) ) {
			$this->markTestSkipped( 'Soap server disabled on CI' );
		}
		$request = $this->getMockBuilder( Request::class )->disableOriginalConstructor()
		->getMock();
		$response = $this->getMockBuilder( Response::class )->disableOriginalConstructor()
		->getMock();
		$request->method( 'getRawRequest' )
		->willReturn( file_get_contents( __DIR__ . '/../Data/ipn_RequestForInformation.xml' ) );

		ob_start();
		$this->soap_listener->execute( $request, $response );
		$getContent = ob_get_contents();
		ob_end_clean();
		$message = $this->jobsAdyenQueue->pop();
		$this->assertNull( $message );
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

	public function testSecondChargebackSOAPMessageReceivedAndAcknowledged() {
		if ( !class_exists( \SoapServer::class ) ) {
			$this->markTestSkipped( 'Soap server disabled on CI' );
		}
		$request = $this->getMockBuilder( Request::class )->disableOriginalConstructor()
		->getMock();
		$response = $this->getMockBuilder( Response::class )->disableOriginalConstructor()
		->getMock();
		$request->method( 'getRawRequest' )
		->willReturn( file_get_contents( __DIR__ . '/../Data/ipn_SecondChargeback.xml' ) );
		$obj = json_decode( file_get_contents( __DIR__ . '/../Data/ipn_SecondChargeback.json' ), true );
		$chargeback = $obj['notificationItems'][0]['NotificationRequestItem'];
		ob_start();
		$this->soap_listener->execute( $request, $response );
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
