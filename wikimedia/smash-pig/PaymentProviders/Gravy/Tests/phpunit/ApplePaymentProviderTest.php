<?php
namespace SmashPig\PaymentProviders\Gravy\Tests\phpunit;

use SmashPig\PaymentData\FinalStatus;
use SmashPig\PaymentProviders\Adyen\ApplePayPaymentProvider;
use SmashPig\PaymentProviders\Gravy\Tests\BaseGravyTestCase;

/**
 * @group Gravy
 */
class ApplePaymentProviderTest extends BaseGravyTestCase {
	/**
	 * @var ApplePayPaymentProvider
	 */
	public $provider;

	public function setUp(): void {
		parent::setUp();
		$this->provider = $this->config->object( 'payment-provider/apple' );
	}

	public function testSuccessfulCreateSession() {
		$responseBody = json_decode( file_get_contents( __DIR__ . '/../Data/apple-create-payment-session-response.json' ), true );
		$params = [
			'validation_url' => 'sample_url',
			'domain_name' => 'sample_domain'
		];
		$this->mockApi->expects( $this->once() )
			->method( 'createPaymentSession' )
			->with( $params )
			->willReturn( $responseBody );

		$response = $this->provider->createPaymentSession( $params );

		$this->assertInstanceOf( '\SmashPig\PaymentProviders\Responses\CreatePaymentSessionResponse',
			$response );
		$this->assertTrue( $response->isSuccessful() );
		$this->assertEquals( $responseBody, $response->getRawResponse() );
	}

	public function testSuccessfulCreatePayment() {
		$responseBody = json_decode( file_get_contents( __DIR__ . '/../Data/apple-create-transaction-success.json' ), true );
		$requestBody = json_decode( file_get_contents( __DIR__ . '/../Data/apple-create-payment-request.json' ), true );
		$params = $this->getCreateTrxnParams( $responseBody['amount'] );
		$requestBody['external_identifier'] = $params['order_id'];
		$requestBody['payment_method']['redirect_url'] = $params['return_url'];
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
		$this->assertFalse( $response->requiresRedirect() );
		$this->assertEquals( $responseBody['payment_method']['approval_url'], $response->getRedirectUrl() );
		$this->assertTrue( $response->isSuccessful() );
		$this->assertEquals( FinalStatus::PENDING_POKE, $response->getStatus() );
		$this->assertEquals( "apple", $response->getPaymentMethod() );
		$this->assertEquals( "visa", $response->getPaymentSubmethod() );
	}

	public function testSuccessfulCreatePaymentFromTokenGuestCheckout() {
		$responseBody = json_decode( file_get_contents( __DIR__ . '/../Data/apple-create-transaction-success.json' ), true );
		$responseBody['is_subsequent_payment'] = true;
		$responseBody['merchant_initiated'] = true;
		$responseBody['payment_source'] = "recurring";
		$requestBody = json_decode( file_get_contents( __DIR__ . '/../Data/apple-create-payment-request.json' ), true );
		$params = $this->getCreateTrxnFromTokenParams( $responseBody['amount'] );
		$requestBody['external_identifier'] = $params['order_id'];
		$requestBody['payment_method']['redirect_url'] = $params['return_url'];

		$this->mockApi->expects( $this->once() )
			->method( 'createPayment' )
			->with( $requestBody )
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
		$this->assertEquals( FinalStatus::PENDING_POKE, $response->getStatus() );
		$this->assertEquals( "apple", $response->getPaymentMethod() );
		$this->assertEquals( "visa", $response->getPaymentSubmethod() );
	}

	public function testSuccessfulPayment() {
		$responseBody = json_decode( file_get_contents( __DIR__ . '/../Data/apple-create-transaction-success.json' ), true );
		$responseBody["status"] = "capture_succeeded";
		$responseBody["intent"] = "capture";
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
		$this->assertEquals( "apple", $response->getPaymentMethod() );
		$this->assertEquals( "visa", $response->getPaymentSubmethod() );
	}

	private function getCreateTrxnParams( ?string $amount = '1299' ) {
		$payment_token = file_get_contents( __DIR__ . '/../Data/apple-payment-token-sample.json' );
		$params = [];
		$params['country'] = 'US';
		$params['currency'] = 'USD';
		$params['amount'] = $amount;
		$ct_id = mt_rand( 100000, 1000009 );
		$params['order_id'] = "$ct_id.1";
		$params['payment_method'] = "apple";
		$params['card_scheme'] = "VISA";
		$params['return_url'] = "https://localhost:9001";

		$donorParams = $this->getCreateDonorParams();
		$donorParams['payment_token'] = $payment_token;
		$params = array_merge( $params, $donorParams );

		return $params;
	}

	private function getCreateTrxnFromTokenParams( $amount ) {
		$params = $this->getCreateTrxnParams( $amount );

		unset( $params['gateway_session_id'] );

		$params['recurring_payment_token'] = "random_token";

		return $params;
	}
}
