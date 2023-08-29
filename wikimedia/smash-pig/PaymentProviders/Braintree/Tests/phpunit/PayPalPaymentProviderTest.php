<?php

namespace SmashPig\PaymentProviders\Braintree\Tests;

use SmashPig\PaymentData\FinalStatus;
use SmashPig\PaymentProviders\Braintree\PaypalPaymentProvider;
use SmashPig\PaymentProviders\Braintree\VenmoPaymentProvider;
use SmashPig\Tests\BaseSmashPigUnitTestCase;

/**
 * @group Braintree
 */
class PayPalPaymentProviderTest extends BaseSmashPigUnitTestCase {
	/**
	 * @var PHPUnit_Framework_MockObject_MockObject
	 */
	protected $api;
	protected $merchantAccounts;

	public function setUp(): void {
		parent::setUp();
		$providerConfig = $this->setProviderConfiguration( 'braintree' );
		$this->api = $this->getMockBuilder( 'SmashPig\PaymentProviders\Braintree\Api' )
			->disableOriginalConstructor()
			->getMock();
		$providerConfig->overrideObjectInstance( 'api', $this->api );
		$this->merchantAccounts = [ 'USD' => 'wikimediafoundation', 'GBP' => 'WMF-GBP' ];
	}

	public function testPaymentWithNoDeviceDataError() {
		$request = [
			"payment_token" => "fake-valid-nonce",
			"order_id" => '123.3',
			"amount" => '1.00',
			"currency" => "USD"
		];

		$provider = new PaypalPaymentProvider( [ 'merchant-accounts' => $this->merchantAccounts ] );
		$response = $provider->createPayment( $request );
		$validationError = $response->getValidationErrors();
		$this->assertEquals( $validationError[0]->getField(), 'device_data' );
	}

	public function testVenmoNotUSDError() {
		$request = [
			"payment_token" => "fake-valid-nonce",
			"order_id" => '123.3',
			"amount" => '1.00',
			"device_data" => '{}',
			"currency" => "EUR"
		];

		$provider = new VenmoPaymentProvider();
		$response = $provider->createPayment( $request );
		$validationError = $response->getValidationErrors();
		$this->assertEquals( $validationError[0]->getField(), 'currency' );
	}

	public function testPaymentWithNotSupportedCurrencyError() {
		$request = [
			"payment_token" => "fake-valid-nonce",
			"order_id" => '123.3',
			"amount" => '1.00',
			"device_data" => '{}',
			"currency" => "CNY"
		];

		$provider = new PaypalPaymentProvider( [ 'merchant-accounts' => $this->merchantAccounts ] );
		$response = $provider->createPayment( $request );
		$validationError = $response->getValidationErrors();
		$this->assertEquals( $validationError[0]->getField(), 'currency' );
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
			"device_data" => '{}',
			'first_name' => "Jimmy",
			'last_name' => "Wales",
			'email' => "mockjwales@wikimedia.org",
			"currency" => 'USD'
		];
		$this->api->expects( $this->once() )
			->method( 'authorizePaymentMethod' )
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

		$provider = new PaypalPaymentProvider( [ 'merchant-accounts' => $this->merchantAccounts ] );
		$response = $provider->createPayment( $request );
		$donor_details = $response->getDonorDetails();
		$this->assertEquals( FinalStatus::PENDING_POKE, $response->getStatus() );
		$this->assertEquals( $txn_id, $response->getGatewayTxnId() );
		$this->assertEquals( $payer[ 'firstName' ], $donor_details->getFirstName() );
		$this->assertEquals( $payer[ 'lastName' ], $donor_details->getLastName() );
		$this->assertEquals( $payer[ 'email' ], $donor_details->getEmail() );
		$this->assertEquals( $payer[ 'phone' ], $donor_details->getPhone() );
	}

	public function testApprovePaymentThrowsExceptionWhenCalledWithoutRequiredFields() {
		$provider = new PaypalPaymentProvider( [ 'merchant-accounts' => $this->merchantAccounts ] );
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

		$provider = new PaypalPaymentProvider( [ 'merchant-accounts' => $this->merchantAccounts ] );
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
			"amount" => '1.00',
			"device_data" => '{}',
			'first_name' => "Jimmy",
			'last_name' => "Wales",
			'email' => "mockjwales@wikimedia.org",
			"currency" => "GBP"
		];
		$this->api->expects( $this->once() )
			->method( 'authorizePaymentMethod' )
			->with( [
				'transaction' => [
					'merchantAccountId' => 'WMF-GBP',
					'amount' => '1.00',
					'orderId' => '123.3',
					'riskData' => [
						'deviceData' => '{}'
					],
					"customerDetails" => [
						"email" => "mockjwales@wikimedia.org",
						'phoneNumber' => ''
					],
					"customFields" => [
						"name" => "fullname",
						"value" => "Jimmy Wales"
					]
				],
				'paymentMethodId' => 'fake-valid-nonce'
			] )
			->willReturn( [
				'data' => [
					'authorizePaymentMethod' => [
						'transaction' => [
							'id' => $txn_id,
							'status' => "SUBMITTED_FOR_SETTLEMENT",
							'paymentMethodSnapshot' => [
								'payer' => $payer,
							]
						]
					] ],
				'extensions' => [
					'requestId' => 'ffea98e0-29d5-49d1-a9fd-ad316192a59c'
				]
			] );

		$provider = new PaypalPaymentProvider( [ 'merchant-accounts' => $this->merchantAccounts ] );
		$response = $provider->createPayment( $request );
		$donor_details = $response->getDonorDetails();
		$this->assertEquals( FinalStatus::COMPLETE, $response->getStatus() );
		$this->assertEquals( $txn_id, $response->getGatewayTxnId() );
		$this->assertEquals( $payer[ 'firstName' ], $donor_details->getFirstName() );
		$this->assertEquals( $payer[ 'lastName' ], $donor_details->getLastName() );
		$this->assertEquals( $payer[ 'email' ], $donor_details->getEmail() );
		$this->assertEquals( $payer[ 'phone' ], $donor_details->getPhone() );
	}

	public function testCreatePaymentValidationErrorWhenCalledWithoutRequiredFields() {
		$provider = new PaypalPaymentProvider( [ 'merchant-accounts' => $this->merchantAccounts ] );
		$requestWithoutPaymentToken = [
			"amount" => '1.00',
			"currency" => 'USD',
			"deviceData" => '{}',
			"order_id" => '123.3'
		];
		$response = $provider->createPayment( $requestWithoutPaymentToken );
		$validationError = $response->getValidationErrors();
		$this->assertEquals( $validationError[0]->getField(), 'payment_token' );

		$requestWithoutAmount = [
			"payment_token" => "fake-valid-nonce",
			"currency" => 'USD',
			"deviceData" => '{}',
			"order_id" => '123.3'
		];
		$response = $provider->createPayment( $requestWithoutAmount );
		$validationError = $response->getValidationErrors();
		$this->assertEquals( $validationError[0]->getField(), 'amount' );

		$requestWithoutOrderId = [
			"payment_token" => "fake-valid-nonce",
			"amount" => '1.00',
			"currency" => 'USD',
			"deviceData" => '{}',
		];
		$response = $provider->createPayment( $requestWithoutOrderId );
		$validationError = $response->getValidationErrors();
		$this->assertEquals( $validationError[0]->getField(), 'order_id' );
	}
}
