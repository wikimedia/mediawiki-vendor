<?php

namespace SmashPig\PaymentProviders\Adyen\Test;

use SmashPig\PaymentData\ErrorCode;
use SmashPig\PaymentData\FinalStatus;
use SmashPig\PaymentProviders\Adyen\PaymentProvider;
use SmashPig\PaymentProviders\Adyen\Tests\BaseAdyenTestCase;

/**
 * @group Adyen
 * @group Recurring
 */
class RecurringPaymentTest extends BaseAdyenTestCase {

	/**
	 * @var PaymentProvider
	 */
	public $provider;

	public function setUp(): void {
		parent::setUp();
		$this->provider = $this->config->object( 'payment-provider/cc' );
	}

	protected function getTestParams() {
		return [
			'recurring' => true,
			'order_id' => 'RECURRING-TEST-' . rand( 0, 10000 ),
			'recurring_payment_token' => 'TEST-TOKEN-123',
			'processor_contact_id' => '1234566767',
			'currency' => 'USD',
			'amount' => '9.99',
		];
	}

	public function testGoodRecurringCreatePaymentCall() {
		$this->mockApi->expects( $this->once() )
			->method( 'createPaymentFromToken' )
			->willReturn( [
				'resultCode' => 'Authorised',
				'pspReference' => '00000000000000AB'
			] );

		$params = $this->getTestParams();

		$createPaymentResponse = $this->provider->createPayment( $params );

		$this->assertInstanceOf( '\SmashPig\PaymentProviders\Responses\CreatePaymentResponse',
			$createPaymentResponse );
		$this->assertCount( 0, $createPaymentResponse->getErrors() );
		$this->assertTrue( $createPaymentResponse->isSuccessful() );
		$this->assertEquals( FinalStatus::PENDING_POKE, $createPaymentResponse->getStatus() );
	}

	/**
	 * @param string $refusalReason
	 * @dataProvider cannotRetryRefusalReasons
	 */
	public function testNonRetryableFailedRecurringCreatePaymentCall( $refusalReason ) {
		$this->mockApi->expects( $this->once() )
			->method( 'createPaymentFromToken' )
			->willReturn( [
				'resultCode' => 'Refused',
				'refusalReason' => $refusalReason,
				'pspReference' => '00000000000000AB'
			] );

		$params = $this->getTestParams();

		$createPaymentResponse = $this->provider->createPayment( $params );

		$this->assertInstanceOf( '\SmashPig\PaymentProviders\Responses\CreatePaymentResponse', $createPaymentResponse );
		$this->assertTrue( $createPaymentResponse->hasErrors() );
		$firstError = $createPaymentResponse->getErrors()[0];
		$this->assertEquals( ErrorCode::DECLINED_DO_NOT_RETRY, $firstError->getErrorCode() );
		$this->assertEquals( $refusalReason, $firstError->getDebugMessage() );
		$this->assertFalse( $createPaymentResponse->isSuccessful() );
	}

	/**
	 * @param string $refusalReason
	 * @dataProvider canRetryRefusalReasons
	 */
	public function testRetryableFailedRecurringCreatePaymentCall( $refusalReason ) {
		$this->mockApi->expects( $this->once() )
			->method( 'createPaymentFromToken' )
			->willReturn( [
				'resultCode' => 'Refused',
				'refusalReason' => $refusalReason,
				'pspReference' => '00000000000000AB'
			] );

		$params = $this->getTestParams();

		$createPaymentResponse = $this->provider->createPayment( $params );

		$this->assertInstanceOf( '\SmashPig\PaymentProviders\Responses\CreatePaymentResponse', $createPaymentResponse );
		$this->assertTrue( $createPaymentResponse->hasErrors() );
		$firstError = $createPaymentResponse->getErrors()[0];
		$this->assertEquals( ErrorCode::DECLINED, $firstError->getErrorCode() );
		$this->assertEquals( $refusalReason, $firstError->getDebugMessage() );
		$this->assertFalse( $createPaymentResponse->isSuccessful() );
	}

	/**
	 * Not sure how we end up with 'invalid card number' when charging a tokenized payment,
	 * but apparently it's possible, so make sure we don't error out.
	 * @return void
	 */
	public function testRecurringFailWithNoResultCode() {
		$this->mockApi->expects( $this->once() )
			->method( 'createPaymentFromToken' )
			->willReturn( [
				'status' => 422,
				'errorCode' => '101',
				'message' => 'Invalid card number',
				'errorType' => 'validation',
				'pspReference' => 'K9ABCDEFGH84J769'
			] );

		$params = $this->getTestParams();

		$createPaymentResponse = $this->provider->createPayment( $params );

		$this->assertInstanceOf( '\SmashPig\PaymentProviders\Responses\CreatePaymentResponse', $createPaymentResponse );
		$this->assertEquals( 'K9ABCDEFGH84J769', $createPaymentResponse->getGatewayTxnId() );
		$this->assertTrue( $createPaymentResponse->hasErrors() );
		$validationError = $createPaymentResponse->getValidationErrors()[0];
		$this->assertEquals( 'card_num', $validationError->getField() );
		$this->assertFalse( $createPaymentResponse->isSuccessful() );
	}

	/**
	 * Refusal codes taken from https://docs.adyen.com/development-resources/test-cards/result-code-testing/adyen-response-codes
	 * @return array
	 */
	public function canRetryRefusalReasons() {
		return [
			[ 'Unknown' ],
			[ 'Refused' ],
			[ 'Acquirer Error' ],
			[ 'Expired Card' ],
			[ 'Issuer Unavailable' ],
			[ 'Not supported' ],
			[ '3D Not Authenticated' ],
			[ 'Not enough balance' ],
			[ 'Pending' ],
			[ 'Cancelled' ],
			[ 'Shopper Cancelled' ],
			[ 'Pin tries exceeded' ],
			[ 'Not Submitted' ],
			[ 'Transaction Not Permitted' ],
			[ 'CVC Declined' ],
			[ 'Declined Non Generic' ],
			[ 'Withdrawal amount exceeded' ],
			[ 'Withdrawal count exceeded' ],
			[ 'Amount partially approved' ],
			[ 'AVS Declined' ],
			[ 'Card requires online pin' ],
			[ 'No checking account available on Card' ],
			[ 'No savings account available on Card' ],
			[ 'Mobile PIN required' ],
			[ 'Contactless fallback' ],
			[ 'Authentication required' ]
		];
	}

	/**
	 * Refusal codes taken from https://docs.adyen.com/development-resources/test-cards/result-code-testing/adyen-response-codes
	 * @return array
	 */
	public function cannotRetryRefusalReasons() {
		return [
			[ 'Acquirer Fraud' ],
			[ 'Blocked Card' ],
			[ 'FRAUD' ],
			[ 'FRAUD-CANCELLED' ],
			[ 'Invalid Amount' ],
			[ 'Invalid Card Number' ],
			[ 'Invalid Pin' ],
			[ 'No Contract Found' ],
			[ 'Pin validation not possible' ],
			[ 'Referral' ],
			[ 'Restricted Card' ],
			[ 'Revocation Of Auth' ],
			[ 'Issuer Suspected Fraud' ]
		];
	}

	public function testGoodRecurringCreateDirectDebitPaymentCall() {
		$this->provider = $this->config->object( 'payment-provider/dd' );
		$params = $this->getTestParams();

		$this->mockApi->expects( $this->once() )
			->method( 'createPaymentFromToken' )
			->willReturn( [
				'resultCode' => 'Received',
				'pspReference' => '00000000000000AB'
			 ] );

		$createPaymentResponse = $this->provider->createPayment( $params );

		$this->assertInstanceOf(
			'\SmashPig\PaymentProviders\Responses\CreatePaymentResponse',
			$createPaymentResponse
		);
		$this->assertCount( 0, $createPaymentResponse->getErrors() );
		$this->assertTrue( $createPaymentResponse->isSuccessful() );
	}

	public function testBadRecurringCreateDirectDebitPaymentCall() {
		$this->provider = $this->config->object( 'payment-provider/dd' );
		$params = $this->getTestParams();

		$this->mockApi->expects( $this->once() )
			->method( 'createPaymentFromToken' )
			->willReturn( [
				'additionalData' => null,
				'fraudResult' => null,
				'refusalReason' => '800 Contract not found',
				'resultCode' => 'Refused',
				'pspReference' => '851584732543280B'
			] );

		$createPaymentResponse = $this->provider->createPayment( $params );

		$this->assertInstanceOf(
			'\SmashPig\PaymentProviders\Responses\CreatePaymentResponse',
			$createPaymentResponse
		);
		$this->assertFalse( $createPaymentResponse->isSuccessful() );
		$this->assertCount( 1, $createPaymentResponse->getErrors() );
		$firstError = $createPaymentResponse->getErrors()[0];
		$this->assertEquals( ErrorCode::DECLINED, $firstError->getErrorCode() );
		$this->assertEquals( '800 Contract not found', $firstError->getDebugMessage() );
	}

	public function testFailRecurringCreatePaymentCallWithNoAutoRescue() {
		$params = $this->getTestParams();
		$msg = [
			'merchantReference' => $params['order_id'],
			'pspReference' => 'testPspReference',
			'resultCode' => 'Refused',
			'success' => false,
			'refusalReason' => 'Issuer Suspected Fraud',
			'additionalData' => [
				'retry.rescueScheduled' => 'false',
				'retry.rescueReference' => null,
			],
		];
		$this->mockApi->expects( $this->once() )
			->method( 'createPaymentFromToken' )
			->willReturn( $msg );

		$createPaymentResponse = $this->provider->createPayment( $params );

		$this->assertInstanceOf( '\SmashPig\PaymentProviders\Responses\CreatePaymentResponse',
			$createPaymentResponse );
		$this->assertFalse( $createPaymentResponse->isSuccessful() );
		$this->assertFalse( $createPaymentResponse->getIsProcessorRetryScheduled() );
		$this->assertNull( $createPaymentResponse->getProcessorRetryRescueReference() );
	}

	public function testFailRecurringCreatePaymentCallWithAutoRescueScheduled() {
		$params = $this->getTestParams();
		$msg = [
			'merchantReference' => $params['order_id'],
			'pspReference' => 'testPspReference',
			'resultCode' => 'Refused',
			'success' => false,
			'refusalReason' => 'Issuer Suspected Fraud',
			'additionalData' => [
				'retry.rescueScheduled' => 'true',
				'retry.rescueReference' => 'testRescueReference',
			],
		];
		$this->mockApi->expects( $this->once() )
			->method( 'createPaymentFromToken' )
			->willReturn( $msg );

		$createPaymentResponse = $this->provider->createPayment( $params );

		$this->assertInstanceOf( '\SmashPig\PaymentProviders\Responses\CreatePaymentResponse',
			$createPaymentResponse );
		$this->assertFalse( $createPaymentResponse->isSuccessful() );
		$this->assertTrue( $createPaymentResponse->getIsProcessorRetryScheduled() );
		$this->assertNotEmpty( $createPaymentResponse->getProcessorRetryRescueReference() );
	}
}
