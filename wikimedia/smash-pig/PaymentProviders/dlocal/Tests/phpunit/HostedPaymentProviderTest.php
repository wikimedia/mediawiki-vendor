<?php

namespace SmashPig\PaymentProviders\dlocal\Tests\phpunit;

use SmashPig\PaymentData\FinalStatus;
use SmashPig\PaymentProviders\dlocal\Api;
use SmashPig\PaymentProviders\dlocal\HostedPaymentProvider;
use SmashPig\Tests\BaseSmashPigUnitTestCase;

/**
 * @group Dlocal
 */
class HostedPaymentProviderTest extends BaseSmashPigUnitTestCase {

	protected $api;

	public function setUp(): void {
		parent::setUp();
		$providerConfig = $this->setProviderConfiguration( 'dlocal' );
		$this->api = $this->getMockBuilder( Api::class )
			->disableOriginalConstructor()
			->getMock();
		$providerConfig->overrideObjectInstance( 'api', $this->api );
	}

	public function testHostedPaymentWithIncompleteParams(): void {
		$request = [
			"order_id" => '123.3',
			"amount" => '1.00',
			"currency" => "USD",
		];

		$provider = new HostedPaymentProvider();
		$response = $provider->createPayment( $request );
		$validationError = $response->getValidationErrors();
		$this->assertTrue( count( $validationError ) > 0 );
		$this->assertFalse( $response->isSuccessful() );
	}

	public function testHostedPaymentWithCompleteParamsSuccess(): void {
		$params = $this->getCreateHostedPaymentRequestParams();
		$this->api->expects( $this->once() )
			->method( 'redirectPayment' )
			->with( $params )
			->willReturn( [
				"id" => "D-4-75c7473a-ab86-4e43-bd39-c840268747d3",
				"amount" => 100,
				"currency" => "MXN",
				"country" => "MX",
				"payment_method_id" => "OX",
				"payment_method_type" => "TICKET",
				"payment_method_flow" => "REDIRECT",
				"created_date" => "2018-12-26T20:37:20.000+0000",
				"status" => "PENDING",
				"status_code" => "100",
				"status_detail" => "The payment was pending",
				"redirect_url" => "https://sandbox.dlocal.com/collect/pay/pay/M-0aa0cc00-094e-11e9-9f92-dbdad3ad0963?xtid=CATH-ST-1545856640-602791137",
				"order_id" => $params['order_id'],
			] );

		$provider = new HostedPaymentProvider();
		$response = $provider->createPayment( $params );
		$validationError = $response->getValidationErrors();
		$this->assertCount( 0, $validationError );
		$this->assertTrue( $response->isSuccessful() );
		$this->assertTrue( $response->requiresRedirect() );
		$this->assertEquals( "https://sandbox.dlocal.com/collect/pay/pay/M-0aa0cc00-094e-11e9-9f92-dbdad3ad0963?xtid=CATH-ST-1545856640-602791137", $response->getRedirectUrl() );
		$this->assertEquals( FinalStatus::PENDING, $response->getStatus() );
	}

	public function testHostedPaymentWithCompleteParamsFailsAndEmptyStatusInResponse(): void {
		$params = $this->getCreateHostedPaymentRequestParams();

		$this->api->expects( $this->once() )
			->method( 'redirectPayment' )
			->with( $params )
			->willReturn( [
				"code" => 5008,
				"message" => "Error happened somewhere",
			] );

		$provider = new HostedPaymentProvider();
		$response = $provider->createPayment( $params );
		$error = $response->getErrors();
		$this->assertCount( 1, $error );
		$this->assertFalse( $response->isSuccessful() );
		$this->assertFalse( $response->requiresRedirect() );
		$this->assertEquals( FinalStatus::UNKNOWN, $response->getStatus() );
	}

	private function getCreateHostedPaymentRequestParams(): array {
		return [
			'order_id' => '123.3',
			'amount' => '100',
			'currency' => 'MXN',
			'country' => 'MX',
			'payment_method_flow' => 'REDIRECT',
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
			'user_ip' => '127.0.0.1',
		];
	}
}
