<?php

namespace SmashPig\PaymentProviders\dlocal\Tests;

use SmashPig\PaymentData\FinalStatus;
use SmashPig\PaymentProviders\dlocal\Api;
use SmashPig\PaymentProviders\dlocal\CardPaymentProvider;
use SmashPig\Tests\BaseSmashPigUnitTestCase;

/**
 * @group Dlocal
 */
class PaymentProviderTest extends BaseSmashPigUnitTestCase {

	protected $api;

	public function setUp() : void {
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
		$data = $this->getCreatePaymentRequestParams();
		$params = $data['params'];
		$transformedParams = $data['transformedParams'];
		$this->api->expects( $this->once() )
				->method( 'authorizePayment' )
				->with( $transformedParams )
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
		$this->assertEquals( FinalStatus::PENDING_POKE, $response->getStatus() );
	}

	public function testPaymentWithCompleteParamsFail(): void {
		$data = $this->getCreatePaymentRequestParams();
		$params = $data['params'];
		$transformedParams = $data['transformedParams'];
		$this->api->expects( $this->once() )
				->method( 'authorizePayment' )
				->with( $transformedParams )
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
		$this->assertEquals( FinalStatus::FAILED, $response->getStatus() );
	}

	public function testPaymentWithCompleteParamsPending(): void {
		$data = $this->getCreatePaymentRequestParams();
		$params = $data['params'];
		$transformedParams = $data['transformedParams'];
		$this->api->expects( $this->once() )
				->method( 'authorizePayment' )
				->with( $transformedParams )
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
						"created_date" => "2018-02-15T15:14:52-00:00",
						"approved_date" => "2018-02-15T15:14:52-00:00",
						"status" => "PENDING",
						"status_code" => "100",
						"status_detail" => "The payment is pending.",
						"order_id" => $params['order_id'],
				] );

		$provider = new CardPaymentProvider();
		$response = $provider->createPayment( $params );
		$error = $response->getErrors();
		$this->assertCount( 0, $error );
		$this->assertTrue( $response->isSuccessful() );
		$this->assertEquals( FinalStatus::PENDING_POKE, $response->getStatus() );
	}

	public function testPaymentWithCompleteParamsFailsDueToUnknownStatus(): void {
		$data = $this->getCreatePaymentRequestParams();
		$params = $data['params'];
		$transformedParams = $data['transformedParams'];
		$this->api->expects( $this->once() )
				->method( 'authorizePayment' )
				->with( $transformedParams )
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
		$this->assertEquals( FinalStatus::FAILED, $response->getStatus() );
	}

	public function testPaymentWithCompleteParamsFailsAndEmptyStatusInResponse(): void {
		$data = $this->getCreatePaymentRequestParams();
		$params = $data['params'];
		$transformedParams = $data['transformedParams'];

		$this->api->expects( $this->once() )
				->method( 'authorizePayment' )
				->with( $transformedParams )
				->willReturn( [
						"code" => 5008,
						"message" => "Token not found or inactive"
				] );

		$provider = new CardPaymentProvider();
		$response = $provider->createPayment( $params );
		$error = $response->getErrors();
		$this->assertCount( 1, $error );
		$this->assertFalse( $response->isSuccessful() );
		$this->assertEquals( FinalStatus::FAILED, $response->getStatus() );
	}

	private function getCreatePaymentRequestParams(): array {
		return [
				"params" => [
						"payment_token" => 'fake-token',
						"order_id" => '123.3',
						"amount" => '1.00',
						"currency" => 'ZAR',
						"country" => 'SA',
						'payment_method' => 'CARD',
						'payment_submethod' => 'DIRECT',
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
				],
				"transformedParams" => [
						'amount' => '1.00',
						'currency' => 'ZAR',
						'country' => 'SA',
						'payment_method_id' => 'CARD',
						'payment_method_flow' => 'DIRECT',
						'order_id' => '1234',
						'card' => [
								'token' => 'fake-token',
								'capture' => false
						],
						'payer' => [
								'name' => 'Lorem Ipsum',
								'email' => 'li@mail.com',
								'document' => '12345',
								'user_reference' => '12345',
								'ip' => '127.0.0.1',
						],
						'address' => [
								'state' => 'lore',
								'city' => 'lore',
								'zip_code' => 'lore',
								'street' => 'lore',
								'number' => 2,
						]
				]
		];
	}
}
