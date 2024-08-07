<?php

namespace SmashPig\PaymentProviders\Gravy\Tests\phpunit;

use SmashPig\PaymentData\FinalStatus;
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

	public function testSuccessfulCreatePaymentCreateDonor() {
		$responseBody = json_decode( file_get_contents( __DIR__ . '/../Data/create-transaction.json' ), true );
		$getDonorResponseBody = json_decode( file_get_contents( __DIR__ . '/../Data/list-buyer.json' ), true );
		$createDonorResponseBody = json_decode( file_get_contents( __DIR__ . '/../Data/create-buyer.json' ), true );

		$getDonorResponseBody['items'] = [];
		$this->mockApi->expects( $this->once() )
			->method( 'getDonor' )
			->willReturn( $getDonorResponseBody );
		$this->mockApi->expects( $this->once() )
			->method( 'createDonor' )
			->willReturn( $createDonorResponseBody );
		$this->mockApi->expects( $this->once() )
			->method( 'createPayment' )
			->willReturn( $responseBody );

		$params = $this->getCreateTrxnParams( $responseBody['checkout_session_id'], $responseBody['amount'] );

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
		$this->assertTrue( $response->isSuccessful() );
	}

	public function testCorrectMappedRiskScores() {
		$responseBody = json_decode( file_get_contents( __DIR__ . '/../Data/create-transaction.json' ), true );
		$gravyResponseMapper = new ResponseMapper();
		$normalizedResponse = $gravyResponseMapper->mapFromCreatePaymentResponse( $responseBody );

		$response = GravyCreatePaymentResponseFactory::fromNormalizedResponse( $normalizedResponse );

		$this->assertInstanceOf( '\SmashPig\PaymentProviders\Responses\CreatePaymentResponse',
			$response );
		$this->assertTrue( $response->isSuccessful() );
		$this->assertEquals( [
			'avs' => 75,
			'cvv' => 0
		], $response->getRiskScores() );
	}

	public function testSuccessfulCreatePaymentFailCreateDonor() {
		$responseBody = json_decode( file_get_contents( __DIR__ . '/../Data/create-transaction.json' ), true );
		$getDonorResponseBody = json_decode( file_get_contents( __DIR__ . '/../Data/list-buyer.json' ), true );
		$createDonorResponseErrorBody = json_decode( file_get_contents( __DIR__ . '/../Data/create-buyer-error-dup.json' ), true );

		$getDonorResponseBody['items'] = [];
		$this->mockApi->expects( $this->once() )
			->method( 'getDonor' )
			->willReturn( $getDonorResponseBody );
		$this->mockApi->expects( $this->once() )
			->method( 'createDonor' )
			->willReturn( $createDonorResponseErrorBody );

		$params = $this->getCreateTrxnParams( $responseBody['checkout_session_id'], $responseBody['amount'] );

		$response = $this->provider->createPayment( $params );

		$this->assertInstanceOf( '\SmashPig\PaymentProviders\Responses\CreatePaymentResponse',
			$response );
		$this->assertFalse( $response->isSuccessful() );
		$errors = $response->getErrors();
		$this->assertCount( 1, $errors );
		$error_code = $createDonorResponseErrorBody['status'];
		$this->assertEquals( ErrorMapper::$errorCodes[$error_code], $errors[0]->getErrorCode() );
	}

	public function testSuccessfulCreatePaymentNoCreateDonor() {
		$responseBody = json_decode( file_get_contents( __DIR__ . '/../Data/create-transaction.json' ), true );
		$getDonorResponseBody = json_decode( file_get_contents( __DIR__ . '/../Data/list-buyer.json' ), true );
		$this->mockApi->expects( $this->once() )
			->method( 'getDonor' )
			->willReturn( $getDonorResponseBody );
		$this->mockApi->expects( $this->once() )
			->method( 'createPayment' )
			->willReturn( $responseBody );

		$params = $this->getCreateTrxnParams( $responseBody['checkout_session_id'], $responseBody['amount'] );

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
		$this->assertTrue( $response->isSuccessful() );
	}

	public function testSuccessfulApprovePayment() {
		$responseBody = json_decode( file_get_contents( __DIR__ . '/../Data/capture-transaction.json' ), true );
		$params = $this->getApproveTrxnParams();

		$this->mockApi->expects( $this->once() )
			->method( 'approvePayment' )
			->with( $params['gateway_txn_id'], [ 'amount' => 1299 ] )
			->willReturn( $responseBody );

		$response = $this->provider->approvePayment( $params );

		$this->assertInstanceOf( '\SmashPig\PaymentProviders\Responses\ApprovePaymentResponse',
			$response );

		$this->assertTrue( $response->isSuccessful() );
		$this->assertEquals( FinalStatus::PENDING, $response->getStatus() );
		$this->assertEquals( $responseBody['id'], $response->getGatewayTxnId() );
	}

	public function testErrorCreatePayment() {
		$apiErrorResponseBody = json_decode( file_get_contents( __DIR__ . '/../Data/create-transaction-api-fail.json' ), true );
		$params = $this->getCreateTrxnParams( 'random-session-id' );
		$getDonorResponseBody = json_decode( file_get_contents( __DIR__ . '/../Data/list-buyer.json' ), true );

		$this->mockApi->expects( $this->once() )
			->method( 'getDonor' )
			->willReturn( $getDonorResponseBody );

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

	public function testDupErrorCreatePayment() {
		$dupTrxnErrorResponseBody = json_decode( file_get_contents( __DIR__ . '/../Data/create-transaction-duplicate-transaction-fail.json' ), true );
		$params = $this->getCreateTrxnParams( 'random-session-id' );
		$getDonorResponseBody = json_decode( file_get_contents( __DIR__ . '/../Data/list-buyer.json' ), true );

		$this->mockApi->expects( $this->once() )
			->method( 'getDonor' )
			->willReturn( $getDonorResponseBody );

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

		$getDonorResponseBody = json_decode( file_get_contents( __DIR__ . '/../Data/list-buyer.json' ), true );
		$this->mockApi->expects( $this->once() )
			->method( 'getDonor' )
			->willReturn( $getDonorResponseBody );

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
		$getDonorResponseBody = json_decode( file_get_contents( __DIR__ . '/../Data/list-buyer.json' ), true );

		$this->mockApi->expects( $this->once() )
			->method( 'getDonor' )
			->willReturn( $getDonorResponseBody );

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
		$this->assertCount( 6, $valErrors );
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

	private function getCreateTrxnParams( string $checkoutSessionId, ?string $donor_id = '123', ?string $amount = '1299' ) {
		$params = [];
		$params['country'] = 'US';
		$params['currency'] = 'USD';
		$params['amount'] = $amount;
		$params['gateway_session_id'] = $checkoutSessionId;
		$params['gateway_donor_id'] = $donor_id;
		$ct_id = mt_rand( 100000, 1000009 );
		$params['order_id'] = "$ct_id.1";

		$donorParams = $this->getCreateDonorParams();

		$params = array_merge( $params, $donorParams );

		return $params;
	}

	private function getApproveTrxnParams( $amount = '12.99' ) {
		return [
			'amount' => $amount,
			'currency' => 'USD',
			'gateway_txn_id' => 'random-id'
		];
	}
}
