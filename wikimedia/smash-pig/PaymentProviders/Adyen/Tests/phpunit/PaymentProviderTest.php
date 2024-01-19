<?php

namespace SmashPig\PaymentProviders\Adyen\Tests\phpunit;

use SmashPig\PaymentData\ErrorCode;
use SmashPig\PaymentProviders\Adyen\CardPaymentProvider;
use SmashPig\PaymentProviders\Adyen\Tests\AdyenTestConfiguration;
use SmashPig\PaymentProviders\Adyen\Tests\BaseAdyenTestCase;

/**
 * @group Adyen
 */
class PaymentProviderTest extends BaseAdyenTestCase {

	/**
	 * @var CardPaymentProvider
	 */
	public $provider;

	public function setUp() : void {
		parent::setUp();
		$this->provider = $this->config->object( 'payment-provider/cc' );
	}

	public function testGoodApprovePayment() {
		$this->mockApi->expects( $this->once() )
			->method( 'approvePayment' )
			->willReturn( AdyenTestConfiguration::getSuccessfulApproveResult() );

		// test params
		$params['gateway_txn_id'] = "CAPTURE-TEST-" . rand( 0, 100 );
		$params['currency'] = 'USD';
		$params['amount'] = '9.99';

		$approvePaymentResponse = $this->provider->approvePayment( $params );

		$this->assertInstanceOf( '\SmashPig\PaymentProviders\Responses\ApprovePaymentResponse',
			$approvePaymentResponse );
		$this->assertEquals( 'received', $approvePaymentResponse->getRawStatus() );
		$this->assertSame( '00000000000000AB', $approvePaymentResponse->getGatewayTxnId() );
		$this->assertTrue( $approvePaymentResponse->isSuccessful() );
		$this->assertTrue( count( $approvePaymentResponse->getErrors() ) == 0 );
	}

	/**
	 * Currently if bad JSON comes back from the ApprovePayment call the
	 * API will json_decode it to null
	 *
	 * @see PaymentProviders/Adyen/Api.php:101
	 *
	 */
	public function testBadApprovePayment() {
		$this->mockApi->expects( $this->once() )
			->method( 'approvePayment' )
			->willReturn( null );

		// test params
		$params['gateway_txn_id'] = "INVALID-ID-0000";
		$params['currency'] = 'USD';
		$params['amount'] = '9.99';

		$approvePaymentResponse = $this->provider->approvePayment( $params );

		$this->assertInstanceOf( '\SmashPig\PaymentProviders\Responses\ApprovePaymentResponse',
			$approvePaymentResponse );
		$this->assertFalse( $approvePaymentResponse->isSuccessful() );
		$this->assertTrue( $approvePaymentResponse->hasErrors() );
		$firstError = $approvePaymentResponse->getErrors()[0];
		$this->assertEquals( ErrorCode::MISSING_REQUIRED_DATA, $firstError->getErrorCode() );
		$this->assertEquals(
			'status element missing from Adyen capture response.',
			$firstError->getDebugMessage()
		);
		$secondError = $approvePaymentResponse->getErrors()[1];
		$this->assertEquals( ErrorCode::NO_RESPONSE, $secondError->getErrorCode() );
	}

	public function testUnknownStatusReturnedForApprovePayment() {
		$this->mockApi->expects( $this->once() )
			->method( 'approvePayment' )
			->willReturn( [
				'status' => '[unknown-status]',
				'pspReference' => '00000000000000AB'
			] );

		// test params
		$params['gateway_txn_id'] = "CAPTURE-TEST-" . rand( 0, 100 );
		$params['currency'] = 'USD';
		$params['amount'] = '9.99';

		$approvePaymentResponse = $this->provider->approvePayment( $params );

		$this->assertInstanceOf( '\SmashPig\PaymentProviders\Responses\ApprovePaymentResponse',
			$approvePaymentResponse );
		$this->assertFalse( $approvePaymentResponse->isSuccessful() );
		$this->assertTrue( $approvePaymentResponse->hasErrors() );
		$firstError = $approvePaymentResponse->getErrors()[0];
		$this->assertInstanceOf( 'SmashPig\Core\PaymentError', $firstError );
		$this->assertEquals( ErrorCode::UNEXPECTED_VALUE, $firstError->getErrorCode() );
		$this->assertEquals(
			'Unknown Adyen status [unknown-status]',
			$firstError->getDebugMessage()
		);
	}

	public function testGoodCancelPayment() {
		$gatewayTxnId = 'CANCEL-TEST-' . rand( 0, 100 );

		$this->mockApi->expects( $this->once() )
			->method( 'cancel' )
			->with( $gatewayTxnId )
			->willReturn( AdyenTestConfiguration::getSuccessfulCancelResult() );

		$cancelPaymentResponse = $this->provider->cancelPayment( $gatewayTxnId );

		$this->assertInstanceOf( '\SmashPig\PaymentProviders\Responses\CancelPaymentResponse',
			$cancelPaymentResponse );
		$this->assertEquals( 'received', $cancelPaymentResponse->getRawStatus() );
		$this->assertSame( '00000000000000AB', $cancelPaymentResponse->getGatewayTxnId() );
		$this->assertTrue( $cancelPaymentResponse->isSuccessful() );
		$this->assertTrue( count( $cancelPaymentResponse->getErrors() ) == 0 );
	}

	/**
	 * Currently if we make a cancel call with an invalid payment id it triggers a
	 * SoapFault within the Api class which is then caught and false is returned
	 *
	 * @see PaymentProviders/Adyen/Api.php:125
	 *
	 */
	public function testBadCancelPayment() {
		$gatewayTxnId = 'CANCEL-TEST-' . rand( 0, 100 );

		$this->mockApi->expects( $this->once() )
			->method( 'cancel' )
			->with( $gatewayTxnId )
			->willReturn( [] );

		$cancelPaymentResponse = $this->provider->cancelPayment( $gatewayTxnId );

		$this->assertInstanceOf( '\SmashPig\PaymentProviders\Responses\CancelPaymentResponse',
			$cancelPaymentResponse );
		$this->assertFalse( $cancelPaymentResponse->isSuccessful() );

		$firstError = $cancelPaymentResponse->getErrors()[0];
		$this->assertInstanceOf( 'SmashPig\Core\PaymentError', $firstError );
		$this->assertEquals( ErrorCode::MISSING_REQUIRED_DATA, $firstError->getErrorCode() );
		$this->assertEquals(
			'cancelResult element missing from Adyen cancel response.',
			$firstError->getDebugMessage()
		);
	}

	public function testGoodCancelAutoRescue() {
		$rescueReference = 'CANCEL-AUTO-RESCUE-TEST-' . rand( 0, 100 );

		$this->mockApi->expects( $this->once() )
			->method( 'cancelAutoRescue' )
			->with( $rescueReference )
			->willReturn( AdyenTestConfiguration::getSuccessfulCancelAutoRescueResult() );

		$cancelAutoRescueResponse = $this->provider->cancelAutoRescue( $rescueReference );

		$this->assertInstanceOf( '\SmashPig\PaymentProviders\Responses\CancelAutoRescueResponse',
			$cancelAutoRescueResponse );
		$this->assertEquals( '[cancel-received]', $cancelAutoRescueResponse->getRawResponse()['response'] );
		$this->assertSame( '00000000000000CR', $cancelAutoRescueResponse->getGatewayTxnId() );
		$this->assertTrue( $cancelAutoRescueResponse->isSuccessful() );
		$this->assertTrue( count( $cancelAutoRescueResponse->getErrors() ) == 0 );
	}

	public function testBadCancelAutoRescue() {
		$rescueReference = 'CANCEL-AUTO-RESCUE-TEST-' . rand( 0, 100 );

		$this->mockApi->expects( $this->once() )
			->method( 'cancelAutoRescue' )
			->with( $rescueReference )
			->willReturn( [] );

		$cancelAutoRescueResponse = $this->provider->cancelAutoRescue( $rescueReference );

		$this->assertInstanceOf( '\SmashPig\PaymentProviders\Responses\CancelAutoRescueResponse',
			$cancelAutoRescueResponse );
		$this->assertFalse( $cancelAutoRescueResponse->isSuccessful() );

		$firstError = $cancelAutoRescueResponse->getErrors()[0];
		$this->assertInstanceOf( 'SmashPig\Core\PaymentError', $firstError );
		$this->assertEquals( ErrorCode::MISSING_REQUIRED_DATA, $firstError->getErrorCode() );
		$this->assertEquals(
			'cancel auto rescue request is not received',
			$firstError->getDebugMessage()
		);
	}

	public function testUnsupportedCard() {
		$this->mockApi->expects( $this->once() )
			->method( 'createPaymentFromEncryptedDetails' )
			->with()
			->willReturn( [
				'status' => 500,
				'errorCode' => '905_1',
				'message' => 'Could not find an acquirer account for the provided txvariant (uatp), currency (NOK), and action (AUTH).',
				'errorType' => 'configuration',
				'pspReference' => 'SZ7VN2XQSCZ28222'
			] );
		$response = $this->provider->createPayment( [
			'currency' => 'EUR',
			'amount' => '23.25',
			'order_id' => '1234.1',
			'encrypted_payment_data' => [
				'encryptedCardNumber' => 'adyenjs_0_1_25$Wzozxz+Xa60jIs/aAyaddayaddayadda',
				'encryptedExpiryMonth' => 'adyenjs_0_1_25$W+Jspf1bZ2AGu6lSetcetera',
				'encryptedExpiryYear' => 'adyenjs_0_1_25$XoUIwK1nyHSn1Hpicandsoforth',
				'encryptedSecurityCode' => 'adyenjs_0_1_25$Sn6D6UB3yLAX+5Sloremipsum',
			],
			'city' => 'Detroit',
			'street_address' => '8952 Grand River Avenue',
			'country' => 'US',
			'description' => 'Wikimedia Foundation',
			'email' => 'wkramer@mc5.net',
			'first_name' => 'Wayne',
			'last_name' => 'Kramer',
			'postal_code' => '48204',
			'return_url' => 'https://paymentstest2.wmcloud.org/index.php?title=Special:AdyenCheckoutGatewayResult&order_id=1234.1&wmf_token=9b5527285f64111d11fb9dc8579ad147%2B%5C',
			'state_province' => 'MI',
			'user_ip' => '127.0.0.1',
		] );
		$this->assertFalse( $response->isSuccessful() );
		$this->assertTrue( $response->hasErrors() );
		$this->assertEquals( 'payment_submethod', $response->getValidationErrors()[0]->getField() );
	}
}
