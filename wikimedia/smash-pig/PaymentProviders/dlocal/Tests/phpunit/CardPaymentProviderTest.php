<?php

namespace SmashPig\PaymentProviders\dlocal\Tests;

use SmashPig\Core\ApiException;
use SmashPig\PaymentData\ErrorCode;
use SmashPig\PaymentData\FinalStatus;
use SmashPig\PaymentProviders\dlocal\Api;
use SmashPig\PaymentProviders\dlocal\CardPaymentProvider;
use SmashPig\PaymentProviders\dlocal\ErrorMapper;
use SmashPig\Tests\BaseSmashPigUnitTestCase;

/**
 * @group Dlocal
 */
class CardPaymentProviderTest extends BaseSmashPigUnitTestCase {

	protected $api;

	public function setUp(): void {
		parent::setUp();
		$providerConfig = $this->setProviderConfiguration( 'dlocal' );
		$this->api = $this->getMockBuilder( Api::class )
				->disableOriginalConstructor()
				->getMock();
		$providerConfig->overrideObjectInstance( 'api', $this->api );
	}

	public function testPaymentWithIncompleteParams(): void {
		$request = [
				"payment_token" => "fake-token",
				"order_id" => '123.3',
				"amount" => '1.00',
				"currency" => "USD"
		];

		$provider = new CardPaymentProvider();
		$response = $provider->createPayment( $request );
		$validationError = $response->getValidationErrors();
		$this->assertTrue( count( $validationError ) > 0 );
		$this->assertFalse( $response->isSuccessful() );
	}

	public function testPaymentWithCompleteParamsSuccess(): void {
		$params = $this->getCreatePaymentRequestParams();
		$gateway_txn_id = "PAY2323243343543";
		$this->api->expects( $this->once() )
			->method( 'cardAuthorizePayment' )
			->with( $params )
			->willReturn( [
						"id" => $gateway_txn_id,
						"amount" => 1,
						"currency" => "ZAR",
						"country" => "SA",
						"payment_method_id" => "CARD",
						"payment_method_type" => "CARD",
						"payment_method_flow" => "DIRECT",
						"card" => [
							"holder_name" => "Lorem Ipsum",
							"expiration_month" => 10,
							"expiration_year" => 2040,
							"last4" => "1111",
							"brand" => "VI"
						],
						  "created_date" => "2018-02-15T15:14:52-00:00",
						  "approved_date" => "2018-02-15T15:14:52-00:00",
						  "status" => "AUTHORIZED",
						  "status_code" => "200",
						  "status_detail" => "The payment was paid.",
						  "order_id" => $params['order_id'],
				] );

		$provider = new CardPaymentProvider();
		$response = $provider->createPayment( $params );
		$validationError = $response->getValidationErrors();
		$this->assertCount( 0, $validationError );
		$this->assertTrue( $response->isSuccessful() );
		$this->assertEquals( $response->getGatewayTxnId(), $gateway_txn_id );
		$this->assertEquals( FinalStatus::PENDING_POKE, $response->getStatus() );
		$this->assertEquals( 'visa', $response->getPaymentSubmethod() );
	}

	public function testPaymentWithCompleteParamsFail(): void {
		$params = $this->getCreatePaymentRequestParams();
		$gateway_txn_id = "PAY2323243343543";
		$this->api->expects( $this->once() )
			->method( 'cardAuthorizePayment' )
			->with( $params )
			->willReturn( [
						"id" => $gateway_txn_id,
						"amount" => 1,
						"currency" => "ZAR",
						"country" => "SA",
						"payment_method_id" => "CARD",
						"payment_method_type" => "CARD",
						"payment_method_flow" => "DIRECT",
						"card" => [
								"holder_name" => "Lorem Ipsum",
								"expiration_month" => 10,
								"expiration_year" => 2040,
								"last4" => "1111",
								"brand" => "VI"
						],
						"created_date" => "2018-02-15T15:14:52-00:00",
						"approved_date" => "2018-02-15T15:14:52-00:00",
						"status" => "REJECTED",
						"status_code" => "300",
						"status_detail" => "The payment was rejected",
						"order_id" => $params['order_id'],
				] );

		$provider = new CardPaymentProvider();
		$response = $provider->createPayment( $params );
		$error = $response->getErrors();
		$this->assertCount( 1, $error );
		$this->assertFalse( $response->isSuccessful() );
		$this->assertEquals( $response->getGatewayTxnId(), $gateway_txn_id );
		$this->assertEquals( FinalStatus::FAILED, $response->getStatus() );
		$this->assertEquals( 'visa', $response->getPaymentSubmethod() );
	}

	public function testPaymentWithCompleteParamsPending(): void {
		$params = $this->getCreatePaymentRequestParams();
		$gateway_txn_id = "PAY2323243343543";
		$this->api->expects( $this->once() )
			->method( 'cardAuthorizePayment' )
			->with( $params )
			->willReturn( [
						"id" => $gateway_txn_id,
						"amount" => 1,
						"currency" => "ZAR",
						"country" => "SA",
						"payment_method_id" => "CARD",
						"payment_method_type" => "CARD",
						"payment_method_flow" => "DIRECT",
						"card" => [
								"holder_name" => "Lorem Ipsum",
								"expiration_month" => 10,
								"expiration_year" => 2040,
								"last4" => "1111",
								"brand" => "VI"
						],
						"created_date" => "2018-02-15T15:14:52-00:00",
						"approved_date" => "2018-02-15T15:14:52-00:00",
						"status" => "AUTHORIZED",
						"status_code" => "100",
						"status_detail" => "The payment is pending.",
						"order_id" => $params['order_id'],
				] );

		$provider = new CardPaymentProvider();
		$response = $provider->createPayment( $params );
		$error = $response->getErrors();
		$this->assertCount( 0, $error );
		$this->assertTrue( $response->isSuccessful() );
		$this->assertEquals( $response->getGatewayTxnId(), $gateway_txn_id );
		$this->assertEquals( FinalStatus::PENDING_POKE, $response->getStatus() );
		$this->assertEquals( 'visa', $response->getPaymentSubmethod() );
	}

	public function testPaymentWithCompleteParamsFailsDueToUnknownStatus(): void {
		$params = $this->getCreatePaymentRequestParams();
		$gateway_txn_id = "PAY2323243343543";
		$this->api->expects( $this->once() )
				->method( 'cardAuthorizePayment' )
				->with( $params )
				->willReturn( [
						"id" => $gateway_txn_id,
						"amount" => 1,
						"currency" => "ZAR",
						"country" => "SA",
						"payment_method_id" => "CARD",
						"payment_method_type" => "CARD",
						"payment_method_flow" => "DIRECT",
						"card" => [
								"holder_name" => "Lorem Ipsum",
								"expiration_month" => 10,
								"expiration_year" => 2040,
								"last4" => "1111",
								"brand" => "VI"
						],
						"created_date" => "2018-02-15T15:14:52-00:00",
						"approved_date" => "2018-02-15T15:14:52-00:00",
						"status" => "UNKNOWN",
						"status_code" => "300",
						"status_detail" => "The payment was rejected.",
						"order_id" => $params['order_id'],
				] );

		$provider = new CardPaymentProvider();
		$response = $provider->createPayment( $params );
		$error = $response->getErrors();
		$this->assertCount( 1, $error );
		$this->assertFalse( $response->isSuccessful() );
		$this->assertEquals( $response->getGatewayTxnId(), $gateway_txn_id );
		$this->assertEquals( FinalStatus::UNKNOWN, $response->getStatus() );
	}

	public function testPaymentWithCompleteParamsFailsAndMissingStatusInResponse(): void {
		$params = $this->getCreatePaymentRequestParams();
		$errorCode = 5008;
		$errorMessage = "Token not found or inactive";
		$this->api->expects( $this->once() )
				->method( 'cardAuthorizePayment' )
				->with( $params )
				->willReturn( [
						"code" => $errorCode,
						"message" => $errorMessage
				] );

		$provider = new CardPaymentProvider();
		$response = $provider->createPayment( $params );
		$error = $response->getErrors();
		$this->assertCount( 1, $error );
		$this->assertEquals( 'Status element missing from dlocal response.', $error[0]->getDebugMessage() );
		$this->assertEquals( ErrorCode::MISSING_REQUIRED_DATA, $error[0]->getErrorCode() );
		$this->assertFalse( $response->isSuccessful() );
		$this->assertEquals( FinalStatus::UNKNOWN, $response->getStatus() );
	}

	public function testPaymentWithCompleteParams3DSecureEnabled(): void {
		$params = $this->getCreatePaymentRequestParams();
		$params['3DSecure'] = true;

		$this->api->expects( $this->once() )
				->method( 'cardAuthorizePayment' )
				->with( $params )
			->willReturn( [
				"id" => "PAY2323243343543",
				"amount" => 1,
				"currency" => "ZAR",
				"country" => "SA",
				"payment_method_id" => "CARD",
				"payment_method_type" => "CARD",
				"payment_method_flow" => "DIRECT",
				"card" => [
					"holder_name" => "Lorem Ipsum",
					"expiration_month" => 10,
					"expiration_year" => 2040,
					"last4" => "1111",
					"brand" => "VI"
				],
				'three_dsecure' =>
					[
						'redirect_url' => 'https://www.example.com/3d-secure-redirect',
					],
				'created_date' => '2023-02-09T14:47:49.000+0000',
				'status' => 'PENDING',
				'status_detail' => 'The payment is pending for 3ds authorization.',
				'status_code' => '101',
				'order_id' => '657434343',
				'notification_url' => 'http://merchant.com/notifications',

			] );

		$provider = new CardPaymentProvider();
		$response = $provider->createPayment( $params );
		$error = $response->getErrors();
		$this->assertCount( 0, $error );
		$this->assertTrue( $response->isSuccessful() );
		$this->assertEquals( FinalStatus::PENDING, $response->getStatus() );
		$this->assertEquals( "https://www.example.com/3d-secure-redirect", $response->getRedirectUrl() );
	}

	public function testApprovePaymentSuccess(): void {
		$params = [
			"gateway_txn_id" => "T-2486-91e73695-3e0a-4a77-8594-f2220f8c6515",
			'amount' => 100,
			'currency' => 'BRL',
			'order_id' => '1234512345',
		];

		$this->api->expects( $this->once() )
			->method( 'capturePayment' )
			->with( $params )
			->willReturn( [
				'id' => 'T-2486-aa9c1884-9f54-409a-9223-ede614b78173',
				'amount' => 100,
				'currency' => 'BRL',
				'country' => 'BR',
				'status' => 'PAID',
				'status_detail' => 'The payment was paid.',
				'status_code' => '200',
				'order_id' => '1234512345',
				'notification_url' => 'http://merchant.com/notifications',
				'authorization_id' => 'T-2486-91e73695-3e0a-4a77-8594-f2220f8c6515',
		] );

		$cardPaymentProvider = new CardPaymentProvider();
		$approvePaymentResponse = $cardPaymentProvider->approvePayment( $params );
		$this->assertTrue( $approvePaymentResponse->isSuccessful() );
		$this->assertEquals( FinalStatus::COMPLETE, $approvePaymentResponse->getStatus() );
	}

	public function testPaymentWithCompleteParamsPendingRecurringSetToTrue(): void {
		$params = $this->getCreatePaymentRequestParams();
		$params['recurring'] = "1";
		$gateway_txn_id = "PAY2323243343543";
		$card_id = "CID-e41c183d-2657-4e82-b39a-b0069c2af657";
		$this->api->expects( $this->once() )
			->method( 'cardAuthorizePayment' )
			->with( $params )
			->willReturn( [
				"id" => $gateway_txn_id,
				"amount" => 1,
				"currency" => "ZAR",
				"country" => "SA",
				"payment_method_id" => "CARD",
				"payment_method_type" => "CARD",
				"payment_method_flow" => "DIRECT",
				"card" => [
					"holder_name" => "Lorem Ipsum",
					"expiration_month" => 10,
					"expiration_year" => 2040,
					"last4" => "1111",
					"brand" => "VI",
					"card_id" => $card_id
				],
				"created_date" => "2018-02-15T15:14:52-00:00",
				"approved_date" => "2018-02-15T15:14:52-00:00",
				"status" => "AUTHORIZED",
				"status_code" => "100",
				"status_detail" => "The payment is pending.",
				"order_id" => $params['order_id'],
			] );

		$provider = new CardPaymentProvider();
		$response = $provider->createPayment( $params );
		$error = $response->getErrors();
		$this->assertCount( 0, $error );
		$this->assertTrue( $response->isSuccessful() );
		$this->assertEquals( $response->getGatewayTxnId(), $gateway_txn_id );
		$this->assertEquals( $response->getRecurringPaymentToken(), $card_id );
		$this->assertEquals( FinalStatus::PENDING_POKE, $response->getStatus() );
		$this->assertEquals( 'visa', $response->getPaymentSubmethod() );
	}

	public function testPaymentWithFiscalNumberValidationError(): void {
		$params = $this->getCreatePaymentRequestParams();
		$exception = new ApiException();
		$exception->setRawErrors( [
			'code' => 5001,
			'message' => 'Invalid parameter: payer.document',
			'param' => 'payer.document',
		] );
		$this->api->expects( $this->once() )
			->method( 'cardAuthorizePayment' )
			->with( $params )
			->will( $this->throwException( $exception ) );
		$provider = new CardPaymentProvider();
		$response = $provider->createPayment( $params );
		$this->assertTrue( $response->hasErrors() );
		$errors = $response->getValidationErrors();
		$this->assertCount( 1, $errors );
		$this->assertSame( 'fiscal_number', $errors[0]->getField() );
	}

	public function testPaymentWithCompleteParamsAndRecurringPaymentToken(): void {
		$params = $this->getCreatePaymentRequestParams();
		unset( $params['payment_token'] );
		$card_id = "CID-e41c183d-2657-4e82-b39a-b0069c2af657";
		$params['recurring_payment_token'] = $card_id;
		$gateway_txn_id = "PAY2323243343543";
		$this->api->expects( $this->once() )
			->method( 'makeRecurringCardPayment' )
			->with( $params )
			->willReturn( [
				"id" => $gateway_txn_id,
				"amount" => 1,
				"currency" => "ZAR",
				"country" => "SA",
				"payment_method_id" => "CARD",
				"payment_method_type" => "CARD",
				"payment_method_flow" => "DIRECT",
				"card" => [
					"holder_name" => "Lorem Ipsum",
					"expiration_month" => 10,
					"expiration_year" => 2040,
					"last4" => "1111",
					"brand" => "VI",
					"card_id" => $card_id
				],
				"created_date" => "2018-02-15T15:14:52-00:00",
				"approved_date" => "2018-02-15T15:14:52-00:00",
				"status" => "PAID",
				"status_code" => "200",
				"status_detail" => "The payment was paid.",
				"order_id" => $params['order_id'],
			] );

		$provider = new CardPaymentProvider();
		$response = $provider->createPayment( $params );
		$error = $response->getErrors();
		$this->assertCount( 0, $error );
		$this->assertTrue( $response->isSuccessful() );
		$this->assertEquals( $response->getGatewayTxnId(), $gateway_txn_id );
		$this->assertEquals( $response->getRecurringPaymentToken(), $card_id );
		$this->assertEquals( FinalStatus::COMPLETE, $response->getStatus() );
		$this->assertEquals( 'visa', $response->getPaymentSubmethod() );
	}

	public function testApprovePaymentFailMissingGatewayTxnId(): void {
		$params = [
			// gateway_txn_id is missing
			'amount' => 100,
			'currency' => 'BRL',
			'order_id' => '1234512345',
		];

		$cardPaymentProvider = new CardPaymentProvider();
		$approvePaymentResponse = $cardPaymentProvider->approvePayment( $params );
		$this->assertFalse( $approvePaymentResponse->isSuccessful() );
		$this->assertEquals( FinalStatus::FAILED, $approvePaymentResponse->getStatus() );
		$this->assertCount( 1, $approvePaymentResponse->getValidationErrors() );
		$this->assertSame( 'gateway_txn_id', $approvePaymentResponse->getValidationErrors()[0]->getField() );
	}

	public function testApprovePaymentWithCompleteParamsFail(): void {
		$gateway_txn_id = "PAY2323243343543";
		$params = [
			'gateway_txn_id' => $gateway_txn_id,
			'amount' => 100,
			'currency' => 'BRL',
			'order_id' => '1234512345',
		];

		$this->api->expects( $this->once() )
			->method( 'capturePayment' )
			->with( $params )
			->willReturn( [
						"id" => $gateway_txn_id,
						"amount" => 1,
						"currency" => "ZAR",
						"country" => "SA",
						"payment_method_id" => "CARD",
						"payment_method_type" => "CARD",
						"payment_method_flow" => "DIRECT",
						"card" => [
								"holder_name" => "Lorem Ipsum",
								"expiration_month" => 10,
								"expiration_year" => 2040,
								"last4" => "1111",
								"brand" => "VI"
						],
						"created_date" => "2018-02-15T15:14:52-00:00",
						"approved_date" => "2018-02-15T15:14:52-00:00",
						"status" => "REJECTED",
						"status_code" => "300",
						"status_detail" => "The payment was rejected",
						"order_id" => $params['order_id'],
				] );

		$provider = new CardPaymentProvider();
		$response = $provider->approvePayment( $params );
		$error = $response->getErrors();
		$this->assertCount( 1, $error );
		$this->assertFalse( $response->isSuccessful() );
		$this->assertEquals( $response->getGatewayTxnId(), $gateway_txn_id );
		$this->assertEquals( FinalStatus::FAILED, $response->getStatus() );
	}

	public function testApprovePaymentWithCompleteParamsFailsAndMissingStatusInResponse(): void {
		$gateway_txn_id = "PAY2323243343543";
		$errorMessage = "placeholder text";
		$errorCode = 5008;
		$params = [
			'gateway_txn_id' => $gateway_txn_id,
			'amount' => 100,
			'currency' => 'BRL',
			'order_id' => '1234512345',
		];
		$this->api->expects( $this->once() )
				->method( 'capturePayment' )
				->with( $params )
				->willReturn( [
						"code" => $errorCode,
						"message" => $errorMessage
				] );

		$provider = new CardPaymentProvider();
		$response = $provider->approvePayment( $params );
		$error = $response->getErrors();
		$this->assertCount( 1, $error );
		$this->assertEquals( "Status element missing from dlocal response.", $error[0]->getDebugMessage() );
		$this->assertEquals( ErrorCode::MISSING_REQUIRED_DATA, $error[0]->getErrorCode() );
		$this->assertFalse( $response->isSuccessful() );
		$this->assertEquals( FinalStatus::UNKNOWN, $response->getStatus() );
	}

	public function testApiAuthorizePaymentReturnsPaymentErrorWithMessageUserBlacklisted(): void {
		$params = $this->getCreatePaymentRequestParams();
		$apiError = [
			"code" => 5014,
			"message" => "User blacklisted"
		];
		$apiException = new ApiException();
		$apiException->setRawErrors( $apiError );
		$this->api->expects( $this->once() )
				->method( 'cardAuthorizePayment' )
				->with( $params )
				->will( $this->throwException( $apiException ) );

		$provider = new CardPaymentProvider();
		$response = $provider->createPayment( $params );
		$error = $response->getErrors();
		$this->assertCount( 1, $error );
		$this->assertEquals( $apiError["message"], $error[0]->getDebugMessage() );
		$this->assertEquals( ErrorMapper::$errorCodes[ $apiError["code"] ], $error[0]->getErrorCode() );
		$this->assertFalse( $response->isSuccessful() );
		$this->assertEquals( FinalStatus::FAILED, $response->getStatus() );
	}

	public function testApiCapturePaymentReturnsPaymentErrorWithMessagePaymentNotFound(): void {
		$gateway_txn_id = "PAY2323243343543";
		$apiError = [
			"code" => 4000,
			"message" => "Payment not found"
		];
		$apiException = new ApiException();
		$apiException->setRawErrors( $apiError );
		$params = [
			'gateway_txn_id' => $gateway_txn_id,
			'amount' => 100,
			'currency' => 'BRL',
			'order_id' => '1234512345',
		];
		$this->api->expects( $this->once() )
				->method( 'capturePayment' )
				->with( $params )
				->will( $this->throwException( $apiException ) );

		$provider = new CardPaymentProvider();
		$response = $provider->approvePayment( $params );
		$error = $response->getErrors();
		$this->assertCount( 1, $error );
		$this->assertEquals( $apiError["message"], $error[0]->getDebugMessage() );
		$this->assertEquals( ErrorMapper::$errorCodes[ $apiError["code"] ], $error[0]->getErrorCode() );
		$this->assertFalse( $response->isSuccessful() );
		$this->assertEquals( FinalStatus::FAILED, $response->getStatus() );
	}

	/**
	 * Test the possibility of the provider changing the key properties in the error message
	 * response.
	 * Expectation is that this change returns a response in the form of a PaymentError indicating
	 * that the parameters have been changed but the flow is not broken for it.
	 */
	public function testApprovePaymentWithCompleteParamsFailsAndEmptyStatusInResponseNoErrorMessage(): void {
		$gateway_txn_id = "PAY2323243343543";
		$errorCode = 5008;
		$params = [
			'gateway_txn_id' => $gateway_txn_id,
			'amount' => 100,
			'currency' => 'BRL',
			'order_id' => '1234512345',
		];
		$this->api->expects( $this->once() )
				->method( 'capturePayment' )
				->with( $params )
				->willReturn( [
					"code" => $errorCode
				] );

		$provider = new CardPaymentProvider();
		$response = $provider->approvePayment( $params );
		$error = $response->getErrors();
		$this->assertCount( 1, $error );
		$this->assertEquals( "Status element missing from dlocal response.", $error[0]->getDebugMessage() );
		$this->assertEquals( ErrorCode::MISSING_REQUIRED_DATA, $error[0]->getErrorCode() );
		$this->assertFalse( $response->isSuccessful() );
		$this->assertEquals( FinalStatus::UNKNOWN, $response->getStatus() );
	}

	public function testPaymentWithUserLimitExceededError(): void {
		$params = $this->getCreatePaymentRequestParams();
		$exception = new ApiException();
		$exception->setRawErrors( [
			'code' => 5006,
			'message' => 'User limit exceeded'
		] );
		$this->api->expects( $this->once() )
			->method( 'cardAuthorizePayment' )
			->with( $params )
			->will( $this->throwException( $exception ) );
		$provider = new CardPaymentProvider();
		$response = $provider->createPayment( $params );

		$this->assertFalse( $response->isSuccessful() );
		$this->assertTrue( $response->hasErrors() );
		$errors = $response->getErrors();
		$this->assertCount( 1, $errors );
		$this->assertSame( 'User limit exceeded', $errors[0]->getDebugMessage() );
	}

	private function getCreatePaymentRequestParams(): array {
		return [
						'payment_token' => 'fake-token',
						'amount' => '1.00',
						'currency' => 'ZAR',
						'country' => 'SA',
						'payment_method' => 'cc',
						'payment_submethod' => 'visa',
						'order_id' => '1234',
						'first_name' => 'Lorem',
						'last_name' => 'Ipsum',
						'email' => 'li@mail.com',
						'fiscal_number' => '12345',
						'contact_id' => '12345',
						'state_province' => 'lore',
						'city' => 'lore',
						'postal_code' => 'lore',
						'street_address' => 'lore',
						'street_number' => 2,
						'user_ip' => '127.0.0.1'
		];
	}
}
