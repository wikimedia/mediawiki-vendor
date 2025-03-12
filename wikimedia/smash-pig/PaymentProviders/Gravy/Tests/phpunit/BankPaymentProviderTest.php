<?php

use SmashPig\PaymentData\FinalStatus;
use SmashPig\PaymentProviders\Gravy\BankPaymentProvider;
use SmashPig\PaymentProviders\Gravy\Tests\BaseGravyTestCase;

/**
 * @group Gravy
 */
class BankPaymentProviderTest extends BaseGravyTestCase {
	/**
	 * @var BankPaymentProvider;
	 */
	public $provider;

	public function setUp() : void {
		parent::setUp();
		$this->provider = $this->config->object( 'payment-provider/dd' );
	}

	public function testSuccessfulCreatePayment() {
		$responseBody = json_decode( file_get_contents( __DIR__ . '/../Data/trustly-create-transaction-success.json' ), true );
		$requestBody = json_decode( file_get_contents( __DIR__ . '/../Data/trustly-create-payment-request.json' ), true );
		$params = $this->getCreateTrxnParams( $responseBody['amount'] );
		$requestBody['external_identifier'] = $params['order_id'];

		$this->mockApi->expects( $this->once() )
			->method( 'createPayment' )
			->with( $requestBody )
			->willReturn( $responseBody );

		$response = $this->provider->createPayment( $params );

		$this->assertInstanceOf( '\SmashPig\PaymentProviders\Responses\CreatePaymentResponse',
			$response );
		$this->assertEquals( $responseBody['amount'] / 100, $response->getAmount() );
		$this->assertEquals( $responseBody['id'], $response->getGatewayTxnId() );
		$this->assertEquals( $responseBody['buyer']['billing_details']['first_name'], $response->getDonorDetails()->getFirstName() );
		$this->assertEquals( $responseBody['buyer']['billing_details']['last_name'], $response->getDonorDetails()->getLastName() );
		$this->assertEquals( $responseBody['buyer']['billing_details']['email_address'], $response->getDonorDetails()->getEmail() );
		$this->assertEquals( $responseBody['buyer']['id'], $response->getDonorDetails()->getCustomerId() );
		$this->assertEquals( $responseBody['buyer']['billing_details']['address']['line1'], $response->getDonorDetails()->getBillingAddress()->getStreetAddress() );
		$this->assertTrue( $response->requiresRedirect() );
		$this->assertEquals( $responseBody['payment_method']['approval_url'], $response->getRedirectUrl() );
		$this->assertTrue( $response->isSuccessful() );
		$this->assertEquals( "dd", $response->getPaymentMethod() );
		$this->assertEquals( "ach", $response->getPaymentSubmethod() );
	}

	public function testSuccessfulCreatePaymentNetbanking() {
		$responseBody = json_decode( file_get_contents( __DIR__ . '/../Data/netbanking-create-transaction-response-pending.json' ), true );
		$requestBody = json_decode( file_get_contents( __DIR__ . '/../Data/netbanking-create-transaction-request.json' ), true );
		$params = $this->getNetbankingCreateTrxnParams( $responseBody['amount'] / 100 );
		$requestBody['external_identifier'] = $params['order_id'];
		$requestBody['payment_method']['redirect_url'] = $params['return_url'];
		$requestBody['buyer']['billing_details']['phone_number'] = $params['phone'];

		$this->mockApi->expects( $this->once() )
			->method( 'createPayment' )
			->with( $requestBody )
			->willReturn( $responseBody );

		$response = $this->provider->createPayment( $params );

		$this->assertInstanceOf( '\SmashPig\PaymentProviders\Responses\CreatePaymentResponse',
			$response );
		$this->assertEquals( $responseBody['amount'] / 100, $response->getAmount() );
		$this->assertEquals( $responseBody['id'], $response->getGatewayTxnId() );
		$this->assertEquals( $responseBody['buyer']['billing_details']['first_name'], $response->getDonorDetails()->getFirstName() );
		$this->assertEquals( $responseBody['buyer']['billing_details']['last_name'], $response->getDonorDetails()->getLastName() );
		$this->assertEquals( $responseBody['buyer']['billing_details']['email_address'], $response->getDonorDetails()->getEmail() );
		$this->assertEquals( $responseBody['buyer']['id'], $response->getDonorDetails()->getCustomerId() );
		$this->assertEquals( $responseBody['buyer']['billing_details']['address']['line1'], $response->getDonorDetails()->getBillingAddress()->getStreetAddress() );
		$this->assertTrue( $response->requiresRedirect() );
		$this->assertEquals( $responseBody['payment_method']['approval_url'], $response->getRedirectUrl() );
		$this->assertTrue( $response->isSuccessful() );
		$this->assertEquals( "bt", $response->getPaymentMethod() );
		$this->assertEquals( "netbanking", $response->getPaymentSubmethod() );
	}

	public function testSuccessfulCreatePaymenPse() {
		$responseBody = json_decode( file_get_contents( __DIR__ . '/../Data/pse-create-transaction-response-pending.json' ), true );
		$requestBody = json_decode( file_get_contents( __DIR__ . '/../Data/pse-create-transaction-request.json' ), true );
		$params = $this->getCreateTrxnParams( $responseBody['amount'] / 100 );
		$params['return_url'] = 'localhost';
		$requestBody['external_identifier'] = $params['order_id'];
		$params['payment_method'] = 'bt';
		$params['payment_submethod'] = 'pse';
		$params['country'] = 'CO';
		$params['currency'] = 'COP';
		$params['fiscal_number'] = '9999999999';

		$this->mockApi->expects( $this->once() )
			->method( 'createPayment' )
			->with( $requestBody )
			->willReturn( $responseBody );

		$response = $this->provider->createPayment( $params );

		$this->assertInstanceOf( '\SmashPig\PaymentProviders\Responses\CreatePaymentResponse',
			$response );
		$this->assertCount( 0, $response->getValidationErrors() );
		$this->assertCount( 0, $response->getErrors() );
		$this->assertEquals( $responseBody['amount'] / 100, $response->getAmount() );
		$this->assertEquals( $responseBody['id'], $response->getGatewayTxnId() );
		$this->assertEquals( $responseBody['buyer']['billing_details']['first_name'], $response->getDonorDetails()->getFirstName() );
		$this->assertEquals( $responseBody['buyer']['billing_details']['last_name'], $response->getDonorDetails()->getLastName() );
		$this->assertEquals( $responseBody['buyer']['billing_details']['email_address'], $response->getDonorDetails()->getEmail() );
		$this->assertEquals( $responseBody['buyer']['id'], $response->getDonorDetails()->getCustomerId() );
		$this->assertEquals( $responseBody['buyer']['billing_details']['address']['line1'], $response->getDonorDetails()->getBillingAddress()->getStreetAddress() );
		$this->assertTrue( $response->requiresRedirect() );
		$this->assertEquals( $responseBody['payment_method']['approval_url'], $response->getRedirectUrl() );
		$this->assertTrue( $response->isSuccessful() );
		$this->assertEquals( "bt", $response->getPaymentMethod() );
		$this->assertEquals( "pse", $response->getPaymentSubmethod() );
	}

	public function testSuccessfulCreatePaymenBcp() {
		$responseBody = json_decode( file_get_contents( __DIR__ . '/../Data/bcp-create-transaction-response-pending.json' ), true );
		$requestBody = json_decode( file_get_contents( __DIR__ . '/../Data/bcp-create-transaction-request.json' ), true );
		$params = $this->getCreateTrxnParams( $responseBody['amount'] / 100 );
		$params['return_url'] = 'localhost';
		$requestBody['external_identifier'] = $params['order_id'];
		$params['payment_method'] = 'bt';
		$params['payment_submethod'] = 'bcp';
		$params['country'] = 'PE';
		$params['currency'] = 'PEN';
		$params['fiscal_number'] = '8480052240';

		$this->mockApi->expects( $this->once() )
			->method( 'createPayment' )
			->with( $requestBody )
			->willReturn( $responseBody );

		$response = $this->provider->createPayment( $params );

		$this->assertInstanceOf( '\SmashPig\PaymentProviders\Responses\CreatePaymentResponse',
			$response );
		$this->assertCount( 0, $response->getValidationErrors() );
		$this->assertCount( 0, $response->getErrors() );
		$this->assertEquals( $responseBody['amount'] / 100, $response->getAmount() );
		$this->assertEquals( $responseBody['id'], $response->getGatewayTxnId() );
		$this->assertEquals( $responseBody['buyer']['billing_details']['first_name'], $response->getDonorDetails()->getFirstName() );
		$this->assertEquals( $responseBody['buyer']['billing_details']['last_name'], $response->getDonorDetails()->getLastName() );
		$this->assertEquals( $responseBody['buyer']['billing_details']['email_address'], $response->getDonorDetails()->getEmail() );
		$this->assertEquals( $responseBody['buyer']['id'], $response->getDonorDetails()->getCustomerId() );
		$this->assertEquals( $responseBody['buyer']['billing_details']['address']['line1'], $response->getDonorDetails()->getBillingAddress()->getStreetAddress() );
		$this->assertTrue( $response->requiresRedirect() );
		$this->assertEquals( $responseBody['payment_method']['approval_url'], $response->getRedirectUrl() );
		$this->assertTrue( $response->isSuccessful() );
		$this->assertEquals( "bt", $response->getPaymentMethod() );
		$this->assertEquals( "bcp", $response->getPaymentSubmethod() );
	}

	public function testSuccessfulPayment() {
		$responseBody = json_decode( file_get_contents( __DIR__ . '/../Data/successful-transaction-trustly.json' ), true );
		$this->mockApi->expects( $this->once() )
			->method( 'getTransaction' )
			->willReturn( $responseBody );

		$params = [
			'gateway_txn_id' => $responseBody['id']
		];

		$response = $this->provider->getLatestPaymentStatus( $params );

		$this->assertInstanceOf( '\SmashPig\PaymentProviders\Responses\PaymentDetailResponse',
			$response );
		$this->assertEquals( $responseBody['amount'] / 100, $response->getAmount() );
		$this->assertEquals( $responseBody['id'], $response->getGatewayTxnId() );
		$this->assertEquals( $responseBody['buyer']['billing_details']['first_name'], $response->getDonorDetails()->getFirstName() );
		$this->assertEquals( $responseBody['buyer']['billing_details']['last_name'], $response->getDonorDetails()->getLastName() );
		$this->assertEquals( $responseBody['buyer']['billing_details']['email_address'], $response->getDonorDetails()->getEmail() );
		$this->assertEquals( $responseBody['buyer']['id'], $response->getDonorDetails()->getCustomerId() );
		$this->assertEquals( $responseBody['buyer']['billing_details']['address']['line1'], $response->getDonorDetails()->getBillingAddress()->getStreetAddress() );
		$this->assertEquals( FinalStatus::COMPLETE, $response->getStatus() );
		$this->assertEquals( $responseBody['payment_method']['approval_url'], $response->getRedirectUrl() );
		$this->assertTrue( $response->isSuccessful() );
		$this->assertEquals( "dd", $response->getPaymentMethod() );
		$this->assertEquals( "ach", $response->getPaymentSubmethod() );
	}

	public function testSuccessfulCreatePaymentFromToken() {
		$responseBody = json_decode( file_get_contents( __DIR__ . '/../Data/trustly-recurring-create-payment-response.json' ), true );
		$params = $this->getCreateTrxnFromTokenParams( $responseBody['amount'] / 100, true );
		$this->mockApi->expects( $this->once() )
			->method( 'createPayment' )
			->with( [
				'amount' => $params['amount'] * 100,
				'currency' => $params['currency'],
				'country' => $params['country'],
				'payment_method' => [
					'method' => 'id',
					'id' => $params['recurring_payment_token']
				],
				'payment_source' => 'recurring',
				'is_subsequent_payment' => true,
				'merchant_initiated' => true,
				'external_identifier' => $params['order_id'],
				'buyer' => [
					'external_identifier' => strtolower( $params['email'] ),
					'billing_details' => [
						'first_name' => $params['first_name'],
						'last_name' => $params['last_name'],
						'email_address' => strtolower( $params['email'] ),
						'phone_number' => $params['phone_number'] ?? null,
						'address' => [
							'city' => $params['city'] ?? null,
							'country' => $params['country'] ?? null,
							'postal_code' => $params['postal_code'] ?? null,
							'state' => $params['state_province'] ?? null,
							'line1' => $params['street_address'] ?? null,
							'line2' => null,
							'organization' => $params['employer'] ?? null
						]
					]
				]
			] )
			->willReturn( $responseBody );

		$response = $this->provider->createPayment( $params );

		$this->assertInstanceOf( '\SmashPig\PaymentProviders\Responses\CreatePaymentResponse',
			$response );
		$this->assertEquals( $responseBody['amount'] / 100, $response->getAmount() );
		$this->assertEquals( $responseBody['id'], $response->getGatewayTxnId() );
		$this->assertEquals( $responseBody['reconciliation_id'], $response->getPaymentOrchestratorReconciliationId() );
		$this->assertEquals( $responseBody['buyer']['billing_details']['first_name'], $response->getDonorDetails()->getFirstName() );
		$this->assertEquals( $responseBody['payment_service_transaction_id'], $response->getBackendProcessorTransactionId() );
		$this->assertEquals( $responseBody['buyer']['billing_details']['last_name'], $response->getDonorDetails()->getLastName() );
		$this->assertEquals( $responseBody['buyer']['billing_details']['email_address'], $response->getDonorDetails()->getEmail() );
		$this->assertEquals( $responseBody['buyer']['id'], $response->getDonorDetails()->getCustomerId() );
		$this->assertEquals( $responseBody['buyer']['billing_details']['address']['line1'], $response->getDonorDetails()->getBillingAddress()->getStreetAddress() );
		$this->assertTrue( $response->isSuccessful() );
	}

	private function getCreateTrxnParams( ?string $amount = '1299' ) {
		$params = [];
		$params['country'] = 'US';
		$params['currency'] = 'USD';
		$params['amount'] = $amount;
		$ct_id = mt_rand( 100000, 1000009 );
		$params['order_id'] = "$ct_id.1";
		$params['payment_method'] = "dd";
		$params['payment_submethod'] = "ach";

		$donorParams = $this->getCreateDonorParams();
		$params = array_merge( $params, $donorParams );

		return $params;
	}

	private function getNetbankingCreateTrxnParams( ?string $amount ) {
		$params = $this->getCreateTrxnParams( $amount );
		$params['payment_method'] = 'bt';
		$params['payment_submethod'] = 'netbanking';
		$params['street_number'] = 10;
		$params['phone'] = "+910123456789";
		$params['country'] = 'IN';
		$params['currency'] = 'INR';
		$params['fiscal_number'] = 'AAAAA9999C';
		$params['city'] = 'Mumbai';
		$params['postal_code'] = '400393';
		$params['state_province'] = 'MH';
		$params['street_address'] = 'New Street';
		$params['return_url'] = '127.0.0.1';
		return $params;
	}

	private function getCreateTrxnFromTokenParams( $amount, $guest = false ) {
		$params = $this->getCreateTrxnParams( $amount );

		unset( $params['gateway_session_id'] );

		$params['recurring'] = 1;
		$params['recurring_payment_token'] = "random_token";
		return $params;
	}
}
