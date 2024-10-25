<?php

namespace SmashPig\PaymentProviders\Gravy\Tests\phpunit;

use SmashPig\PaymentProviders\Gravy\CardPaymentProvider;
use SmashPig\PaymentProviders\Gravy\Factories\GravyCreatePaymentResponseFactory;
use SmashPig\PaymentProviders\Gravy\Mapper\ErrorMapper;
use SmashPig\PaymentProviders\Gravy\Mapper\ResponseMapper;
use SmashPig\PaymentProviders\Gravy\Tests\BaseGravyTestCase;

/**
 * @group Gravy
 */
class CardPaymentProviderTest extends BaseGravyTestCase {

	/**
	 * @var CardPaymentProvider
	 */
	public $provider;

	public function setUp() : void {
		parent::setUp();
		$this->provider = $this->config->object( 'payment-provider/cc' );
	}

	public function testCorrectMappedRiskScores() {
		$responseBody = json_decode( file_get_contents( __DIR__ . '/../Data/create-transaction.json' ), true );
		$gravyResponseMapper = new ResponseMapper();
		$normalizedResponse = $gravyResponseMapper->mapFromPaymentResponse( $responseBody );

		$response = GravyCreatePaymentResponseFactory::fromNormalizedResponse( $normalizedResponse );

		$this->assertInstanceOf( '\SmashPig\PaymentProviders\Responses\CreatePaymentResponse',
			$response );
		$this->assertTrue( $response->isSuccessful() );
		$this->assertEquals( [
			'avs' => 75,
			'cvv' => 0
		], $response->getRiskScores() );
	}

	public function testSuccessfulCreatePaymentFromTokenWithProcessorContactId() {
		$responseBody = json_decode( file_get_contents( __DIR__ . '/../Data/create-transaction.json' ), true );
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
				'buyer_id' => $params['processor_contact_id'],
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

	public function testSuccessfulCreatePaymentFromTokenGuestCheckout() {
		$responseBody = json_decode( file_get_contents( __DIR__ . '/../Data/create-transaction.json' ), true );
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

	public function testErrorCreatePayment() {
		$apiErrorResponseBody = json_decode( file_get_contents( __DIR__ . '/../Data/create-transaction-api-fail.json' ), true );
		$params = $this->getCreateTrxnParams( 'random-session-id' );

		$this->mockApi->expects( $this->once() )
			->method( 'createPayment' )
			->willReturn( $apiErrorResponseBody );

		$response = $this->provider->createPayment( $params );

		$this->assertInstanceOf( '\SmashPig\PaymentProviders\Responses\CreatePaymentResponse',
			$response );
		$this->assertFalse( $response->isSuccessful() );
		$errors = $response->getErrors();
		$this->assertCount( 1, $errors );
		$error_code = $apiErrorResponseBody['status'];
		$this->assertEquals( ErrorMapper::$errorCodes[$error_code], $errors[0]->getErrorCode() );
	}

	public function testError3DSecureCreatePayment() {
		$apiErrorResponseBody = json_decode( file_get_contents( __DIR__ . '/../Data/create-transaction-3dsecure-error.json' ), true );
		$params = $this->getCreateTrxnParams( 'random-session-id' );

		$this->mockApi->expects( $this->once() )
			->method( 'createPayment' )
			->willReturn( $apiErrorResponseBody );

		$response = $this->provider->createPayment( $params );

		$this->assertInstanceOf( '\SmashPig\PaymentProviders\Responses\CreatePaymentResponse',
			$response );
		$this->assertFalse( $response->isSuccessful() );
		$errors = $response->getErrors();
		$this->assertCount( 1, $errors );
		$error_code = $apiErrorResponseBody['three_d_secure']['error_data']['code'];
		$description = $apiErrorResponseBody['three_d_secure']['error_data']['description'];
		$detail = $apiErrorResponseBody['three_d_secure']['error_data']['detail'];
		$this->assertEquals( ErrorMapper::getError( $error_code ), $errors[0]->getErrorCode() );
		$this->assertEquals( $description . ':' . $detail, $errors[0]->getDebugMessage() );
	}

	public function testDupErrorCreatePayment() {
		$dupTrxnErrorResponseBody = json_decode( file_get_contents( __DIR__ . '/../Data/create-transaction-duplicate-transaction-fail.json' ), true );
		$params = $this->getCreateTrxnParams( 'random-session-id' );

		$this->mockApi->expects( $this->once() )
			->method( 'createPayment' )
			->willReturn( $dupTrxnErrorResponseBody );

		$response = $this->provider->createPayment( $params );

		$this->assertInstanceOf( '\SmashPig\PaymentProviders\Responses\CreatePaymentResponse',
			$response );
		$this->assertFalse( $response->isSuccessful() );
		$errors = $response->getErrors();
		$this->assertCount( 1, $errors );
		$error_code = $dupTrxnErrorResponseBody['status'];
		$this->assertEquals( ErrorMapper::$errorCodes[$error_code], $errors[0]->getErrorCode() );
	}

	public function testFailedRequestErrorCreatePayment() {
		$requestErrorResponseBody = json_decode( file_get_contents( __DIR__ . '/../Data/create-transaction-request-fail.json' ), true );
		$params = $this->getCreateTrxnParams( 'random-session-id' );

		$this->mockApi->expects( $this->once() )
			->method( 'createPayment' )
			->willReturn( $requestErrorResponseBody );

		$response = $this->provider->createPayment( $params );

		$this->assertInstanceOf( '\SmashPig\PaymentProviders\Responses\CreatePaymentResponse',
			$response );
		$this->assertFalse( $response->isSuccessful() );
		$errors = $response->getErrors();
		$this->assertCount( 1, $errors );
		$error_code = $requestErrorResponseBody['status'];
		$this->assertEquals( ErrorMapper::$errorCodes[$error_code], $errors[0]->getErrorCode() );
	}

	public function testValidationErrorCreatePayment() {
		$validationErrorResponseBody = json_decode( file_get_contents( __DIR__ . '/../Data/create-transaction-validation-fail.json' ), true );
		$params = $this->getCreateTrxnParams( 'random-session-id' );

		$this->mockApi->expects( $this->once() )
			->method( 'createPayment' )
			->willReturn( $validationErrorResponseBody );

		$response = $this->provider->createPayment( $params );

		$this->assertInstanceOf( '\SmashPig\PaymentProviders\Responses\CreatePaymentResponse',
			$response );
		$this->assertFalse( $response->isSuccessful() );
		$errors = $response->getErrors();
		$this->assertCount( 1, $errors );
		$error_code = $validationErrorResponseBody['status'];
		$this->assertEquals( ErrorMapper::$errorCodes[$error_code], $errors[0]->getErrorCode() );
	}

	public function testValidationErrorCreatePaymentBeforeApiCall() {
		$params = [
			'gateway_session_id' => 'random-session-id'
		];

		$response = $this->provider->createPayment( $params );

		$this->assertInstanceOf( '\SmashPig\PaymentProviders\Responses\CreatePaymentResponse',
			$response );
		$this->assertFalse( $response->isSuccessful() );
		$valErrors = $response->getValidationErrors();
		$errors = $response->getErrors();
		$this->assertCount( 7, $valErrors );
		$this->assertCount( 0, $errors );
	}

	public function testSuccessfulCreateSession() {
		$responseBody = json_decode( file_get_contents( __DIR__ . '/../Data/new-checkout-session-response.json' ), true );
		$this->mockApi->expects( $this->once() )
			->method( 'createPaymentSession' )
			->willReturn( $responseBody );

		$response = $this->provider->createPaymentSession();

		$this->assertInstanceOf( '\SmashPig\PaymentProviders\Responses\CreatePaymentSessionResponse',
			$response );
		$this->assertTrue( $response->isSuccessful() );
		$this->assertEquals( $responseBody['id'], $response->getPaymentSession() );
		$this->assertEquals( $responseBody, $response->getRawResponse() );
	}

	public function testErrorCreatePaymentSession() {
		$responseBody = json_decode( file_get_contents( __DIR__ . '/../Data/new-checkout-session-fail-response.json' ), true );
		$this->mockApi->expects( $this->once() )
			->method( 'createPaymentSession' )
			->willReturn( $responseBody );

		$response = $this->provider->createPaymentSession();

		$this->assertInstanceOf( '\SmashPig\PaymentProviders\Responses\CreatePaymentSessionResponse',
			$response );
		$this->assertFalse( $response->isSuccessful() );
		$this->assertEquals( $responseBody, $response->getRawResponse() );
		$errors = $response->getErrors();
		$this->assertCount( 1, $errors );
		$error_code = $responseBody['status'];
		$this->assertEquals( ErrorMapper::$errorCodes[$error_code], $errors[0]->getErrorCode() );
	}

	private function getCreateTrxnParams( string $checkoutSessionId, ?string $amount = '1299' ) {
		$params = [];
		$params['country'] = 'US';
		$params['currency'] = 'USD';
		$params['amount'] = $amount;
		$params['gateway_session_id'] = $checkoutSessionId;
		$ct_id = mt_rand( 100000, 1000009 );
		$params['order_id'] = "$ct_id.1";

		$donorParams = $this->getCreateDonorParams();

		$params = array_merge( $params, $donorParams );

		return $params;
	}

	private function getCreateTrxnFromTokenParams( $amount, $guest = false ) {
		$params = $this->getCreateTrxnParams( "", $amount );

		unset( $params['gateway_session_id'] );

		$params['recurring'] = 1;
		$params['recurring_payment_token'] = "random_token";
		if ( !$guest ) {
			$params['processor_contact_id'] = "random_contact_id";
		}

		return $params;
	}
}
