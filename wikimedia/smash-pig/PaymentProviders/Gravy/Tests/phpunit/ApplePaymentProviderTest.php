<?php
namespace SmashPig\PaymentProviders\Gravy\Tests\phpunit;

use PHPUnit\Framework\MockObject\Exception;
use SmashPig\PaymentData\FinalStatus;
use SmashPig\PaymentData\PaymentMethod;
use SmashPig\PaymentProviders\Gravy\ApplePayPaymentProvider;
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
		$requestBody = json_decode( file_get_contents( __DIR__ . '/../Data/recurring-payment-request.json' ), true );

		$params = $this->getCreateTrxnFromTokenParams( $responseBody['amount'] );
		$requestBody['amount'] = $responseBody['amount'] * 100;
		$requestBody['payment_method']['id'] = 'random_token';
		$requestBody['external_identifier'] = $params['order_id'];
		$requestBody['statement_descriptor']['description'] = 'Wikimedia Foundation';
		$requestBody['user_ip'] = '12.34.56.78';

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
		$this->assertEquals( "apple", $response->getPaymentMethod() );
		$this->assertEquals( "visa", $response->getPaymentSubmethod() );
	}

	/**
	 * @throws Exception
	 */
	public function testCreatePaymentSessionApiCallSDKError() {
		$stringSDKResponse = 'Request failed validation';
		$params = [
			'validation_url' => 'http://sample.com',
			'domain_name' => 'sample_domain'
		];
		// Mock the Gravy SDK client to return a cURL error string
		$api = $this->createApiInstance();
		// Mock the Gravy SDK client to return a cURL error string
		$mockGravyClient = $this->createMock( \Gr4vy\Gr4vyConfig::class );
		$this->setMockGravyClient( $mockGravyClient, $api );
		$mockGravyClient->expects( $this->exactly( 2 ) )
			->method( 'newApplePaySession' )
			->with( $params )
			->willReturn( $stringSDKResponse );

		// Test the complete flow: Provider -> API -> SDK (returns unexpected error) -> bubbles back up
		$providerResult = $this->provider->createPaymentSession( $params );

		// Verify the provider correctly handles the error and returns the right response
		$this->assertInstanceOf( '\SmashPig\PaymentProviders\Responses\CreatePaymentSessionResponse',
			$providerResult );
		$this->assertFalse( $providerResult->isSuccessful() );
		$this->assertEquals( "Create apple Payment Session response: (http://sample.com) {$stringSDKResponse}", $providerResult->getNormalizedResponse()['description'], 'Error message should normalized to string' );

		// Also verify the API layer converts the string error correctly
		$apiResult = $api->createPaymentSession( $params, PaymentMethod::APPLE );
		$this->assertIsArray( $apiResult, 'API should convert unexpected error string to error array' );
		$this->assertArrayHasKey( 'type', $apiResult, 'API should convert unexpected error string to error array' );
		$this->assertArrayHasKey( 'message', $apiResult, 'API should convert unexpected error string to error array' );
		$this->assertEquals( 'error', $apiResult['type'], 'API should return error type' );
		$this->assertEquals( "Create apple Payment Session response: (http://sample.com) {$stringSDKResponse}", $apiResult['message'], 'API should return string error message' );
	}

	/**
	 * Confirm token payments are successful in Brazil.
	 *
	 * This was  previously failing due to the code adding fiscal number as a required field.
	 *
	 * @return void
	 */
	public function testCreatePaymentFromTokenInBrazil(): void {
		// set params for Brazil
		$brazilianRecurringPaymentParams = $this->getCreateTrxnFromTokenParams( amount:1000 );
		$brazilianRecurringPaymentParams['country'] = 'BR';
		$brazilianRecurringPaymentParams['currency'] = 'BRL';

		$expectedApiRequest = json_decode( file_get_contents( __DIR__ . '/../Data/recurring-payment-request.json' ), true );
		$expectedApiRequest['amount'] = 100000;
		$expectedApiRequest['payment_method']['id'] = 'random_token';
		$expectedApiRequest['external_identifier'] = $brazilianRecurringPaymentParams['order_id'];
		$expectedApiRequest['country'] = 'BR';
		$expectedApiRequest['currency'] = 'BRL';
		$expectedApiRequest['statement_descriptor']['description'] = 'Wikimedia Foundation';
		$expectedApiRequest['buyer']['billing_details']['address']['country'] = 'BR';
		$expectedApiRequest['user_ip'] = '12.34.56.78';

		$mockApiResponse = json_decode( file_get_contents( __DIR__ . '/../Data/apple-create-transaction-success.json' ), true );
		$mockApiResponse['is_subsequent_payment'] = true;
		$mockApiResponse['merchant_initiated'] = true;
		$mockApiResponse['payment_source'] = "recurring";

		$this->mockApi->expects( $this->once() )
			->method( 'createPayment' )
			->with( $expectedApiRequest )
			->willReturn( $mockApiResponse );

		$response = $this->provider->createPayment( $brazilianRecurringPaymentParams );

		$this->assertEquals( $mockApiResponse['amount'] / 100, $response->getAmount() );
		$this->assertEquals( $mockApiResponse['id'], $response->getGatewayTxnId() );
		$this->assertEquals( $mockApiResponse['reconciliation_id'], $response->getPaymentOrchestratorReconciliationId() );
		$this->assertEquals( $mockApiResponse['buyer']['billing_details']['first_name'], $response->getDonorDetails()->getFirstName() );
		$this->assertEquals( $mockApiResponse['payment_service_transaction_id'], $response->getBackendProcessorTransactionId() );
		$this->assertEquals( $mockApiResponse['buyer']['billing_details']['last_name'], $response->getDonorDetails()->getLastName() );
		$this->assertEquals( $mockApiResponse['buyer']['billing_details']['email_address'], $response->getDonorDetails()->getEmail() );
		$this->assertEquals( $mockApiResponse['buyer']['id'], $response->getDonorDetails()->getCustomerId() );
		$this->assertEquals( $mockApiResponse['buyer']['billing_details']['address']['line1'], $response->getDonorDetails()->getBillingAddress()->getStreetAddress() );
		$this->assertTrue( $response->isSuccessful() );
		$this->assertEquals( FinalStatus::PENDING_POKE, $response->getStatus() );
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
		$params['description'] = "Wikimedia Foundation";
		$params['return_url'] = "https://localhost:9001";

		$donorParams = $this->getCreateDonorParams();
		$donorParams['payment_token'] = $payment_token;
		$params = array_merge( $params, $donorParams );

		return $params;
	}

	private function getCreateTrxnFromTokenParams( $amount ) {
		$ct_id = mt_rand( 100000, 1000009 );
		return [
			'recurring_payment_token' => 'random_token',
			'amount' => $amount,
			'country' => 'US',
			'currency' => 'USD',
			'first_name' => 'Lorem',
			'last_name' => 'Ipsum',
			'email' => 'test@test.com',
			'order_id' => "$ct_id.1",
			'installment' => 'recurring',
			'description' => 'Wikimedia Foundation',
			'recurring' => true,
			'user_ip' => '12.34.56.78',
		];
	}
}
