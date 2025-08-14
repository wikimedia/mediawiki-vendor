<?php

namespace SmashPig\PaymentProviders\Gravy\Tests\phpunit;

use SmashPig\PaymentData\FinalStatus;
use SmashPig\PaymentProviders\Gravy\BankPaymentProvider;
use SmashPig\PaymentProviders\Gravy\Tests\BaseGravyTestCase;

/**
 * @group Gravy
 */
class DirectDepositPaymentProviderTest extends BaseGravyTestCase {
	/**
	 * @var BankPaymentProvider
	 */
	public $provider;

	public function setUp(): void {
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

	public function testSuccessfulPayment() {
		$responseBody = json_decode( file_get_contents( __DIR__ . '/../Data/successful-transaction-trustly.json' ), true );
		$this->mockApi->expects( $this->once() )
			->method( 'getTransaction' )
			->willReturn( $responseBody );

		$params = [
			'gateway_txn_id' => $responseBody['id']
		];

		$response = $this->provider->getLatestPaymentStatus( $params );

		$this->assertInstanceOf( '\SmashPig\PaymentProviders\Responses\PaymentProviderExtendedResponse',
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
				"statement_descriptor" => [
					"description" => "Wikimedia Foundation"
				],
				'buyer' => [
					'external_identifier' => $params['email'],
					'billing_details' => [
						'email_address' => $params['email'],
					],
				]
			] )
			->willReturn( $responseBody );

		$response = $this->provider->createPayment( $params );

		$this->assertInstanceOf( '\SmashPig\PaymentProviders\Responses\CreatePaymentResponse',
			$response );
		$this->assertEquals( $responseBody['amount'] / 100, $response->getAmount() );
		$this->assertEquals( $responseBody['id'], $response->getGatewayTxnId() );
		$this->assertEquals( $responseBody['reconciliation_id'], $response->getPaymentOrchestratorReconciliationId() );
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
		$params['description'] = "Wikimedia Foundation";

		$donorParams = $this->getCreateDonorParams();
		$params = array_merge( $params, $donorParams );

		return $params;
	}

	private function getCreateTrxnFromTokenParams( $amount, $guest = false ) {
		$params = $this->getCreateTrxnParams( $amount );

		unset( $params['gateway_session_id'] );
		$params['description'] = "Wikimedia Foundation";

		$params['recurring'] = 1;
		$params['recurring_payment_token'] = "random_token";
		return $params;
	}
}
