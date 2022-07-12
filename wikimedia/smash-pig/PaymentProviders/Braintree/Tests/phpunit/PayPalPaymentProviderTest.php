<?php

namespace SmashPig\PaymentProviders\Braintree\Tests;

use SmashPig\PaymentData\FinalStatus;
use SmashPig\PaymentProviders\Braintree\PaypalPaymentProvider;
use SmashPig\Tests\BaseSmashPigUnitTestCase;

/**
 * @group Braintree
 */
class PayPalPaymentProviderTest extends BaseSmashPigUnitTestCase {
	/**
	 * @var PHPUnit_Framework_MockObject_MockObject
	 */
	protected $api;

	public function setUp(): void {
		parent::setUp();
		$providerConfig = $this->setProviderConfiguration( 'braintree' );
		$this->api = $this->getMockBuilder( 'SmashPig\PaymentProviders\Braintree\Api' )
			->disableOriginalConstructor()
			->getMock();
		$providerConfig->overrideObjectInstance( 'api', $this->api );
	}

	public function testAuthorizePayment() {
		$txn_id = "dHJhbnNhY3Rpb25fYXIxMTNuZzQ";
		$payer = [
			'firstName' => "Jimmy",
			'lastName' => "Wales",
			'email' => "mockjwales@wikimedia.org",
			'phone' => "1-800-wikimedia"
		];
		$request = [
			"payment_token" => "fake-valid-nonce",
			"order_id" => '123.3',
			"amount" => '1.00',
		];
		$this->api->expects( $this->once() )
			->method( 'authorizePayment' )
			->willReturn( [
				'data' => [
					'authorizePaymentMethod' => [
						'transaction' => [
							'id' => $txn_id,
							'status' => "AUTHORIZED",
							'paymentMethodSnapshot' => [
								'payer' => $payer
							]
						]
					] ],
				'extensions' => [
					'requestId' => 'ffea98e0-29d5-49d1-a9fd-ad316192a59c'
				]
			] );

		$provider = new PaypalPaymentProvider();
		$response = $provider->createPayment( $request );
		$donor_details = $response->getDonorDetails();
		$this->assertEquals( FinalStatus::PENDING_POKE, $response->getStatus() );
		$this->assertEquals( $txn_id, $response->getGatewayTxnId() );
		$this->assertEquals( $payer[ 'firstName' ], $donor_details->getFirstName() );
		$this->assertEquals( $payer[ 'lastName' ], $donor_details->getLastName() );
		$this->assertEquals( $payer[ 'email' ], $donor_details->getEmail() );
		$this->assertEquals( $payer[ 'phone' ], $donor_details->getPhone() );
	}

	public function testAuthorizePaymentThrowsExceptionWhenCalledWithoutRequiredFields() {
		$provider = new PaypalPaymentProvider();
		$requestWithoutPaymentToken = [
			"amount" => '1.00',
			"order_id" => '123.3'
		];
		$this->expectException( \InvalidArgumentException::class );
		$provider->createPayment( $requestWithoutPaymentToken );
		$requestWithoutAmount = [
			"payment_token" => "fake-valid-nonce",
			"order_id" => '123.3'
		];
		$this->expectException( \InvalidArgumentException::class );
		$provider->createPayment( $requestWithoutAmount );
		$requestWithoutOrderId = [
			"payment_token" => "fake-valid-nonce",
			"amount" => '1.00'
		];
		$this->expectException( \InvalidArgumentException::class );
		$provider->createPayment( $requestWithoutOrderId );
	}

	public function testApprovePaymentThrowsExceptionWhenCalledWithoutRequiredFields() {
		$provider = new PaypalPaymentProvider();
		$requestWithoutTransactionId = [];
		$this->expectException( \InvalidArgumentException::class );
		$provider->approvePayment( $requestWithoutTransactionId );
	}

	public function testApprovePayment() {
		$txn_id = "dHJhbnNhY3Rpb25fYXIxMTNuZzQ";
		$request = [
			"gateway_txn_id" => $txn_id,
		];
		$this->api->expects( $this->once() )
			->method( 'captureTransaction' )
			->willReturn( [
				'data' => [
					'captureTransaction' => [
						'transaction' => [
							'id' => $txn_id,
							'status' => "SUBMITTED_FOR_SETTLEMENT",
						]
					] ],
				'extensions' => [
					'requestId' => 'ffea98e0-29d5-49d1-a9fd-ad316192a59c'
				]
			] );

		$provider = new PaypalPaymentProvider();
		$response = $provider->approvePayment( $request );
		$this->assertEquals( FinalStatus::COMPLETE, $response->getStatus() );
		$this->assertEquals( $txn_id, $response->getGatewayTxnId() );
	}

	public function testCreatePayment() {
		$txn_id = "dHJhbnNhY3Rpb25fYXIxMTNuZzQ";
		$payer = [
			'firstName' => "Jimmy",
			'lastName' => "Wales",
			'email' => "mockjwales@wikimedia.org",
			'phone' => "1-800-wikimedia"
		];
		$request = [
			"payment_token" => "fake-valid-nonce",
			"order_id" => '123.3',
			"amount" => '1.00'
		];
		$this->api->expects( $this->once() )
			->method( 'authorizePayment' )
			->willReturn( [
				'data' => [
					'authorizePaymentMethod' => [
						'transaction' => [
							'id' => $txn_id,
							'status' => "SUBMITTED_FOR_SETTLEMENT",
							'paymentMethodSnapshot' => [
								'payer' => $payer
							]
						]
					] ],
				'extensions' => [
					'requestId' => 'ffea98e0-29d5-49d1-a9fd-ad316192a59c'
				]
			] );

		$provider = new PaypalPaymentProvider();
		$response = $provider->createPayment( $request );
		$donor_details = $response->getDonorDetails();
		$this->assertEquals( FinalStatus::COMPLETE, $response->getStatus() );
		$this->assertEquals( $txn_id, $response->getGatewayTxnId() );
		$this->assertEquals( $payer[ 'firstName' ], $donor_details->getFirstName() );
		$this->assertEquals( $payer[ 'lastName' ], $donor_details->getLastName() );
		$this->assertEquals( $payer[ 'email' ], $donor_details->getEmail() );
		$this->assertEquals( $payer[ 'phone' ], $donor_details->getPhone() );
	}

	public function testCreatePaymentThrowsExceptionWhenCalledWithoutRequiredFields() {
		$provider = new PaypalPaymentProvider();
		$requestWithoutPaymentToken = [
			"amount" => '1.00',
			"order_id" => '123.3'
		];
		$this->expectException( \InvalidArgumentException::class );
		$provider->createPayment( $requestWithoutPaymentToken );
		$requestWithoutAmount = [
			"payment_token" => "fake-valid-nonce",
			"order_id" => '123.3'
		];
		$this->expectException( \InvalidArgumentException::class );
		$provider->createPayment( $requestWithoutAmount );
		$requestWithoutOrderId = [
			"payment_token" => "fake-valid-nonce",
			"amount" => '1.00'
		];
		$this->expectException( \InvalidArgumentException::class );
		$provider->createPayment( $requestWithoutOrderId );
	}
}
