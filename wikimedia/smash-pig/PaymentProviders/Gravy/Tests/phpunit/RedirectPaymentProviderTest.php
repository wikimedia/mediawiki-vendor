<?php

use SmashPig\PaymentData\FinalStatus;
use SmashPig\PaymentProviders\Gravy\Tests\BaseGravyTestCase;

/**
 * @group Gravy
 */
class RedirectPaymentProviderTest extends BaseGravyTestCase {
	/**
	 * @var RedirectPaymentProvider;
	 */
	public $provider;

	public function setUp() : void {
		parent::setUp();
		$this->provider = $this->config->object( 'payment-provider/venmo' );
	}

	public function testSuccessfulCreatePaymentCreateDonor() {
		$responseBody = json_decode( file_get_contents( __DIR__ . '/../Data/venmo-create-transaction-success.json' ), true );

		$this->mockApi->expects( $this->once() )
			->method( 'createPayment' )
			->willReturn( $responseBody );

		$params = $this->getCreateTrxnParams( $responseBody['amount'] );

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
		$this->assertEquals( "venmo", $response->getPaymentMethod() );
		$this->assertSame( '', $response->getPaymentSubmethod() );
		$this->assertSame( FinalStatus::PENDING, $response->getStatus() );
	}

	public function testSuccessfulAuthorizationVenmo() {
		$responseBody = json_decode( file_get_contents( __DIR__ . '/../Data/venmo-authorize-transaction-successful.json' ), true );
		$this->mockApi->expects( $this->once() )
			->method( 'getTransaction' )
			->willReturn( $responseBody );

		$response = $this->provider->getLatestPaymentStatus( [
			'gateway_txn_id' => $responseBody['id']
		] );

		$this->assertInstanceOf( '\SmashPig\PaymentProviders\Responses\PaymentDetailResponse',
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
		$this->assertEquals( "venmo", $response->getPaymentMethod() );
		$this->assertSame( '', $response->getPaymentSubmethod() );
		$this->assertSame( FinalStatus::PENDING_POKE, $response->getStatus() );
		$this->assertSame( $responseBody['payment_method']['label'], $response->getDonorDetails()->getUserName() );
	}

	public function testSuccessfulCreatePaymentCreateDonorNoBuyerApproval() {
		$responseBody = json_decode( file_get_contents( __DIR__ . '/../Data/venmo-create-transaction-success.json' ), true );
		$responseBody['status'] = 'authorization_succeeded';
		$params = [
			'gateway_txn_id' => 'random_txn_id'
		];

		$this->mockApi->expects( $this->once() )
			->method( 'getTransaction' )
			->with( $params )
			->willReturn( $responseBody );

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
		$this->assertTrue( $response->requiresRedirect() );
		$this->assertEquals( $responseBody['payment_method']['approval_url'], $response->getRedirectUrl() );
		$this->assertTrue( $response->isSuccessful() );
		$this->assertEquals( "venmo", $response->getPaymentMethod() );
		$this->assertSame( '', $response->getPaymentSubmethod() );
		$this->assertEquals( "braintree", $response->getBackendProcessor() );
		$this->assertSame( FinalStatus::PENDING_POKE, $response->getStatus() );
	}

	public function testSuccessfulApprovePaymentCreateDonorSuccessfulAuth() {
		$responseBody = json_decode( file_get_contents( __DIR__ . '/../Data/venmo-approve-transaction-success.json' ), true );
		$params = [
			'gateway_txn_id' => 'random_txn_id'
		];

		$this->mockApi->expects( $this->once() )
			->method( 'getTransaction' )
			->with( $params )
			->willReturn( $responseBody );

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
		$this->assertTrue( $response->requiresRedirect() );
		$this->assertEquals( $responseBody['payment_method']['approval_url'], $response->getRedirectUrl() );
		$this->assertTrue( $response->isSuccessful() );
		$this->assertEquals( "venmo", $response->getPaymentMethod() );
		$this->assertSame( '', $response->getPaymentSubmethod() );
		$this->assertEquals( "braintree", $response->getBackendProcessor() );
		$this->assertSame( FinalStatus::COMPLETE, $response->getStatus() );
		$this->assertSame( $responseBody['payment_method']['label'], $response->getDonorDetails()->getUserName() );
	}

	private function getCreateTrxnParams( ?string $amount = '1299' ) {
		$params = [];
		$params['country'] = 'US';
		$params['currency'] = 'USD';
		$params['amount'] = $amount;
		$ct_id = mt_rand( 100000, 1000009 );
		$params['order_id'] = "$ct_id.1";
		$params['payment_method'] = "venmo";
		$params['payment_submethod'] = "";

		$donorParams = $this->getCreateDonorParams();
		$params = array_merge( $params, $donorParams );

		return $params;
	}

	public function testSuccessfulCreatePaymentFromTokenNoCreateDonorNoGetDonor() {
		$responseBody = json_decode( file_get_contents( __DIR__ . '/../Data/venmo-create-transaction-success.json' ), true );
		$params = $this->getCreateTrxnFromTokenParams( $responseBody['amount'] / 100 );
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
				'buyer_id' => $params['processor_contact_id']
			] )
			->willReturn( $responseBody );

		$response = $this->provider->createPayment( $params );

		$this->assertInstanceOf( '\SmashPig\PaymentProviders\Responses\CreatePaymentResponse',
			$response );
		$this->assertEquals( $responseBody['amount'] / 100, $response->getAmount() );
		$this->assertEquals( $responseBody['id'], $response->getGatewayTxnId() );
		$this->assertEquals( $responseBody['buyer']['billing_details']['first_name'], $response->getDonorDetails()->getFirstName() );
		$this->assertEquals( $responseBody['payment_service_transaction_id'], $response->getBackendProcessorTransactionId() );
		$this->assertEquals( $responseBody['buyer']['billing_details']['last_name'], $response->getDonorDetails()->getLastName() );
		$this->assertEquals( $responseBody['buyer']['billing_details']['email_address'], $response->getDonorDetails()->getEmail() );
		$this->assertEquals( $responseBody['buyer']['id'], $response->getDonorDetails()->getCustomerId() );
		$this->assertEquals( $responseBody['buyer']['billing_details']['address']['line1'], $response->getDonorDetails()->getBillingAddress()->getStreetAddress() );
		$this->assertTrue( $response->isSuccessful() );
	}

	private function getCreateTrxnFromTokenParams( $amount ) {
		$params = $this->getCreateTrxnParams( $amount );

		unset( $params['gateway_session_id'] );

		$params['recurring'] = 1;
		$params['recurring_payment_token'] = "random_token";
		$params['processor_contact_id'] = "random_contact_id";

		return $params;
	}
}
