<?php

namespace SmashPig\PaymentProviders\dlocal\Tests;

use SmashPig\Core\ApiException;
use SmashPig\Core\Http\CurlWrapper;
use SmashPig\PaymentProviders\dlocal\Api;
use SmashPig\Tests\BaseSmashPigUnitTestCase;

/**
 * @group Dlocal
 */
class ApiTest extends BaseSmashPigUnitTestCase {

	/**
	 * @var Api
	 */
	public $api;

	public function setUp(): void {
		parent::setUp();
		$testingProviderConfiguration = $this->setProviderConfiguration( 'dlocal' );
		$testingProviderConfiguration->overrideObjectInstance( 'curl/wrapper', $this->curlWrapper );

		$this->api = new Api( [
			'endpoint' => 'http://example.com',
			'login' => 'test_login',
			'trans-key' => 'test_dg$3434534E',
			'secret' => 'test_ITSASECRET',
			'callback_url' => 'http://example.com',
			'notification_url' => 'http://example.com',
			'version' => '2.1',
		] );
	}

	/**
	 * TODO: This test should be moved and the visibility of Api::makeApiCall()
	 * changed to protected once we implement the first PaymentProvider action
	 * which internally call Api::makeApiCall().
	 *
	 * For now, this test confirms the behaviour of the code available.
	 *
	 * @return void
	 */
	public function testApiCallSetsRequiredRequestHeaders(): void {
		// curlWrapper::execute() is called within Api::makeApiCall()
		// via OutboundRequest::execute(). I did consider mocking
		// OutboundRequest, but it looks like we typically mock the
		// CurlWrapper for this scenario, which is one level lower.
		$this->curlWrapper->expects( $this->once() )
			->method( 'execute' )
			->with(
				$this->anything(), // url
				$this->anything(), // method
				$this->callback( function ( $headers ) {
					$this->assertArrayHasKey( 'X-Date', $headers );
					$this->assertArrayHasKey( 'X-Login', $headers );
					$this->assertArrayHasKey( 'X-Trans-Key', $headers );
					$this->assertArrayHasKey( 'Content-Type', $headers );
					$this->assertArrayHasKey( 'X-Version', $headers );
					$this->assertArrayHasKey( 'User-Agent', $headers );
					$this->assertArrayHasKey( 'Authorization', $headers );
					return true; // if we get here, the headers were set.
				} )
			)
			->willReturn( [
				'status' => 200,
				'body' => '{"result":"test"}',
			] );

		// headers are generated during the call to makeApiCall
		$this->api->makeApiCall();
	}

	public function testApiCallSetsRequiredFormatDateHeader(): void {
		$this->curlWrapper->expects( $this->once() )
			->method( 'execute' )
			->with(
				$this->anything(), // url
				$this->anything(), // method
				$this->callback( function ( $headers ) {
					$this->assertArrayHasKey( 'X-Date', $headers );

					// confirm X-Date string matches format set in docs https://docs.dlocal.com/reference/payins-security#headers
					// e.g. 2023-01-16T14:35:56.990Z
					$expectedDateFormat = 'Y-m-d\TH:i:s.v\Z';
					$dateFromString = \DateTime::createFromFormat( $expectedDateFormat, $headers['X-Date'] );
					$this->assertNotFalse( $dateFromString ); // returns false when string doesn't match format
					$this->assertEquals( $dateFromString->format( $expectedDateFormat ),  $headers['X-Date'] );
					return true; // if we get here, the date header was good were set.
				} )
			)
			->willReturn( [
				'status' => 200,
				'body' => '{"result":"test"}',
			] );

		// headers are generated during the call to makeApiCall
		$this->api->makeApiCall();
	}

	public function testApiCallGeneratesCorrectHMACSignature(): void {
		$emptyParams = [];

		// curlWrapper::execute() is called within Api::makeApiCall()
		// via OutboundRequest::execute(). I did consider mocking
		// OutboundRequest, but it looks like we typically mock the
		// CurlWrapper for this scenario, which is one level lower.
		$this->curlWrapper->expects( $this->once() )
			->method( 'execute' )
			->with(
				$this->anything(), // url
				$this->anything(), // method
				$this->callback( function ( $headers ) use ( $emptyParams ) {
					// generate the signature here using the expected inputs
					$secret = 'test_ITSASECRET';
					$signatureInput = 'test_login' . $headers['X-Date'] . json_encode( $emptyParams );
					$calculatedSignature = hash_hmac( 'sha256', $signatureInput, $secret );
					// dLocal signatures have a text prefix which needs to be in the header
					$signatureTextPrefix = 'V2-HMAC-SHA256, Signature: ';
					$expectedSignatureValue = $signatureTextPrefix . $calculatedSignature;

					// compare generated signature with the signature in the headers
					$this->assertEquals( $expectedSignatureValue, $headers['Authorization'] );
					return true; // if we get here, the headers were set.
				} )
			)
			->willReturn( [
				'status' => 200,
				'body' => '{"result":"test"}',
			] );

		// headers are generated during the call to makeApiCall
		$this->api->makeApiCall();
	}

	/**
	 * @see PaymentProviders/dlocal/Tests/Data/payment-methods.response
	 */
	public function testGetPaymentMethods(): void {
		$mockResponse = $this->prepareMockResponse( 'payment-methods.response', 200 );
		$this->curlWrapper->expects( $this->once() )
			->method( 'execute' )
			->with(
				$this->equalTo( 'http://example.com/payments-methods?country=MX' ),
				$this->equalTo( 'GET' )
			)->willReturn( $mockResponse );

		$results = $this->api->getPaymentMethods( 'MX' ); // MX is Mexico

		$expectedPaymentMethod = [
			'id' => 'OX',
			'type' => 'TICKET',
			'name' => 'Oxxo',
			'logo' => 'https://pay.dlocal.com/views/2.0/images/payments/OX.png',
			'allowed_flows' =>
				[
					0 => 'REDIRECT',
				],
		];

		// the first result for Mexico should be Oxxo
		$this->assertEquals( $expectedPaymentMethod, $results[0] );
	}

	/**
	 * @see PaymentProviders/dlocal/Tests/Data/authorize-payment.response
	 */
	public function testAuthorizePayments(): void {
		$params = $this->getAuthorizePaymentRequestParams();

		$apiParams = $params['params'];
		$transformedParams = $params['transformedParams'];

		$mockResponse = $this->prepareMockResponse( 'authorize-payment.response', 200 );
		$this->curlWrapper->expects( $this->once() )
			->method( 'execute' )
			->with(
				$this->equalTo( 'http://example.com/payments' ), // url
				$this->equalTo( 'POST' ), // method
				$this->anything(),
				$this->callback( function ( $body ) use ( $transformedParams ) {
					// request body should be a json formatted string of the mapped params
					$this->assertEquals( json_encode( $transformedParams ), $body );
					return true;
				} )
			)->willReturn( $mockResponse );

		$results = $this->api->authorizePayment( $apiParams );
	}

	public function testtestAuthorizePayment3DSecure() {
		$this->markTestIncomplete();
	}

		/**
		 * @see PaymentProviders/dlocal/Tests/Data/redirect-payment.response
		 */
	public function testRedirectPayment(): void {
		$params = $this->getRedirectPaymentRequestParams();

		$apiParams = $params['params'];
		$transformedParams = $params['transformedParams'];

		$mockResponse = $this->prepareMockResponse( 'redirect-payment.response', 200 );
		$this->curlWrapper->expects( $this->once() )
			->method( 'execute' )
			->with(
				$this->equalTo( 'http://example.com/payments' ), // url
				$this->equalTo( 'POST' ), // method
				$this->anything(),
				$this->callback( function ( $body ) use ( $transformedParams ) {
					// request body should be a json formatted string of the mapped params
					$this->assertEquals( json_encode( $transformedParams ), $body );
					return true;
				} )
			)->willReturn( $mockResponse );

		$results = $this->api->authorizePayment( $apiParams );
		$this->assertSame( "100", $results["status_code"] );
	}

	public function testRedirectPaymentWithSpecificPaymentMethod(): void {
		$params = $this->getRedirectPaymentRequestParams();

		// add in the specific payment method id 'OX' which is used for Oxxo payments
		// in Mexico https://docs.dlocal.com/docs/mexico
		$params['params']['payment_method_id'] = 'OX';
		$apiParams = $params['params'];

		$mockResponse = $this->prepareMockResponse( 'redirect-payment-specific-method-id.response', 200 );
		$this->curlWrapper->expects( $this->once() )
			->method( 'execute' )
			->with(
				$this->equalTo( 'http://example.com/payments' ), // url
				$this->equalTo( 'POST' ), // method
				$this->anything(),
				$this->callback( function ( $requestParams ) {
					// confirm payment_method_id is present in generated request params
					$requestParamsArray = json_decode( $requestParams, true );
					$this->assertEquals( "OX", $requestParamsArray['payment_method_id'] );
					return true;
				} )
			)->willReturn( $mockResponse );

		$results = $this->api->authorizePayment( $apiParams );
		$this->assertSame( "100", $results["status_code"] );
		$this->assertSame( "PENDING", $results["status"] );
		$this->assertSame( "PQ", $results["payment_method_id"] );
		$this->assertSame( "TICKET", $results["payment_method_type"] );
		$this->assertSame( "REDIRECT", $results["payment_method_flow"] );
	}

	public function testCapturePaymentMapsApiParamsCorrectly(): void {
		$apiParams = [
			"gateway_txn_id" => "T-2486-91e73695-3e0a-4a77-8594-f2220f8c6515",
			'amount' => 100,
			'currency' => 'BRL',
			'order_id' => '1234512345',
		];

		// gateway_txn_id should get mapped to authorization_id
		$expectedMappedParams = [
			"authorization_id" => "T-2486-91e73695-3e0a-4a77-8594-f2220f8c6515",
			'amount' => 100,
			'currency' => 'BRL',
			'order_id' => '1234512345',
		];

		$mockResponse = $this->prepareMockResponse( 'capture-payment.response', 200 );
		$this->curlWrapper->expects( $this->once() )
			->method( 'execute' )
			->with(
				$this->equalTo( 'http://example.com/payments' ), // url
				$this->equalTo( 'POST' ), // method
				$this->anything(),
				$this->callback( function ( $body ) use ( $expectedMappedParams ) {
					// request body should be a json formatted string of the mapped params
					$this->assertEquals( json_encode( $expectedMappedParams ), $body );
					return true;
				} )
			)->willReturn( $mockResponse );

		$capturePaymentResult = $this->api->capturePayment( $apiParams );

		$this->assertEquals( $apiParams['gateway_txn_id'], $capturePaymentResult['authorization_id'] );
		$this->assertEquals( $apiParams['amount'], $capturePaymentResult['amount'] );
		$this->assertEquals( $apiParams['currency'], $capturePaymentResult['currency'] );
		$this->assertEquals( $apiParams['order_id'], $capturePaymentResult['order_id'] );
	}

	public function testCapturePaymentSuccess(): void {
		$apiParams = [
			"gateway_txn_id" => "T-2486-91e73695-3e0a-4a77-8594-f2220f8c6515",
			'amount' => 100,
			'currency' => 'BRL',
			'order_id' => '1234512345',
		];

		$mockResponse = $this->prepareMockResponse( 'capture-payment.response', 200 );
		$this->curlWrapper->expects( $this->once() )
			->method( 'execute' )
			->with(
				$this->equalTo( 'http://example.com/payments' ), // url
				$this->equalTo( 'POST' ), // method
				$this->anything(),
			)->willReturn( $mockResponse );

		$capturePaymentResult = $this->api->capturePayment( $apiParams );

		$this->assertEquals( 'PAID', $capturePaymentResult['status'] );
		$this->assertEquals( 'The payment was paid.', $capturePaymentResult['status_detail'] );
		$this->assertEquals( 200, $capturePaymentResult['status_code'] );

		$this->assertEquals( $apiParams['gateway_txn_id'], $capturePaymentResult['authorization_id'] );
		$this->assertEquals( $apiParams['amount'], $capturePaymentResult['amount'] );
		$this->assertEquals( $apiParams['currency'], $capturePaymentResult['currency'] );
		$this->assertEquals( $apiParams['order_id'], $capturePaymentResult['order_id'] );
	}

	public function testCapturePaymentExceptionOnMissingAuthId(): void {
		$this->expectException( \InvalidArgumentException::class );
		$this->expectExceptionMessage( "gateway_txn_id is a required field" );

		// gateway_txn_id missing from apiParams
		$apiParams = [
			'amount' => 100,
			'currency' => 'BRL',
			'order_id' => '1234512345',
		];

		$this->api->capturePayment( $apiParams );
	}

	public function testCapturePaymentExceptionOnPaymentNotFound(): void {
		$this->expectException( ApiException::class );
		$this->expectExceptionMessage( 'Response Error(404) {"code":4000,"message":"Payment not found"}' );

		$apiParams = [
			"gateway_txn_id" => "T-INVALID-TOKEN",
		];

		$mockResponse = $this->prepareMockResponse( 'capture-payment-fail-invalid-token.response', 404 );
		$this->curlWrapper->expects( $this->once() )
			->method( 'execute' )
			->with(
				$this->equalTo( 'http://example.com/payments' ), // url
				$this->equalTo( 'POST' ), // method
				$this->anything()
			)->willReturn( $mockResponse );

		$this->api->capturePayment( $apiParams );
	}

	/**
	 * This failure happens if you attempt to capture a payment that has previous been captured for the full amount
	 * authorized.
	 */
	public function testCapturePaymentExceptionOnAmountExceeded(): void {
		$this->expectException( ApiException::class );
		$this->expectExceptionMessage( 'Response Error(400) {"code":5007,"message":"Amount exceeded"}' );

		$apiParams = [
			"gateway_txn_id" => "T-2486-91e73695-3e0a-4a77-8594-f2220f8c6515",
			'amount' => 100,
			'currency' => 'BRL',
			'order_id' => '1234512345',
		];

		$mockResponse = $this->prepareMockResponse( 'capture-payment-fail-amount-exceeded.response', 400 );
		$this->curlWrapper->expects( $this->once() )
			->method( 'execute' )
			->with(
				$this->equalTo( 'http://example.com/payments' ), // url
				$this->equalTo( 'POST' ), // method
				$this->anything()
			)->willReturn( $mockResponse );

		$this->api->capturePayment( $apiParams );
	}

	public function testGetPaymentStatusPending(): void {
		$gatewayTxnId = "D-2486-5bc9c596-f3b6-4b7c-bf3c-432276030cd9";

		$mockResponse = $this->prepareMockResponse( 'get-payment-status-pending.response', 200 );
		$this->curlWrapper->expects( $this->once() )
			->method( 'execute' )
			->with(
				$this->equalTo( 'http://example.com/payments/' . $gatewayTxnId . '/status' ), // url
				$this->equalTo( 'GET' ), // method
				$this->anything()
			)->willReturn( $mockResponse );

		$paymentStatus = $this->api->getPaymentStatus( $gatewayTxnId );

		$this->assertEquals( 'D-2486-5bc9c596-f3b6-4b7c-bf3c-432276030cd9', $paymentStatus['id'] );
		$this->assertEquals( 'PENDING', $paymentStatus['status'] );
		$this->assertEquals( 'The payment is pending.', $paymentStatus['status_detail'] );
		$this->assertEquals( 100, $paymentStatus['status_code'] );
	}

	public function testGetPaymentStatusPaid(): void {
		$gatewayTxnId = "D-2486-5bc9c596-f3b6-4b7c-bf3c-432276030cd9";

		$mockResponse = $this->prepareMockResponse( 'get-payment-status-paid.response', 200 );
		$this->curlWrapper->expects( $this->once() )
			->method( 'execute' )
			->with(
				$this->equalTo( 'http://example.com/payments/' . $gatewayTxnId . '/status' ), // url
				$this->equalTo( 'GET' ), // method
				$this->anything()
			)->willReturn( $mockResponse );

		$paymentStatus = $this->api->getPaymentStatus( $gatewayTxnId );

		$this->assertEquals( 'D-2486-5bc9c596-f3b6-4b7c-bf3c-432276030cd9', $paymentStatus['id'] );
		$this->assertEquals( 'PAID', $paymentStatus['status'] );
		$this->assertEquals( 'The payment was paid.', $paymentStatus['status_detail'] );
		$this->assertEquals( 200, $paymentStatus['status_code'] );
	}

	public function testGetPaymentStatusUnknownPaymentIdThrowsException(): void {
		$gatewayTxnId = "D-INVALID-5bc9c596-f3b6-4b7c-bf3c-432276030cd9";

		$mockResponse = $this->prepareMockResponse( 'get-payment-status-unknown-payment-id.response', 404 );
		$this->curlWrapper->expects( $this->once() )
			->method( 'execute' )
			->with(
				$this->equalTo( 'http://example.com/payments/' . $gatewayTxnId . '/status' ), // url
				$this->equalTo( 'GET' ), // method
				$this->anything()
			)->willReturn( $mockResponse );

		$this->expectException( ApiException::class );
		$this->expectExceptionMessage( 'Response Error(404) {"code":4000,"message":"Payment not found"}' );

		$this->api->getPaymentStatus( $gatewayTxnId );
	}

	public function testCancelPaymentSuccess(): void {
		$gatewayTxnId = "T-2486-0ef6f3b6-544f-4734-9ee8-b8a7130cd8c6";

		$mockResponse = $this->prepareMockResponse( 'cancel-payment-status-cancelled.response', 200 );
		$this->curlWrapper->expects( $this->once() )
			->method( 'execute' )
			->with(
				$this->equalTo( 'http://example.com/payments/' . $gatewayTxnId . '/cancel' ), // url
				$this->equalTo( 'POST' ), // method
				$this->anything()
			)->willReturn( $mockResponse );

		$cancelPayment = $this->api->cancelPayment( $gatewayTxnId );

		$this->assertEquals( 'T-2486-0ef6f3b6-544f-4734-9ee8-b8a7130cd8c6', $cancelPayment['id'] );
		$this->assertEquals( 'CANCELLED', $cancelPayment['status'] );
		$this->assertEquals( 'The payment was cancelled.', $cancelPayment['status_detail'] );
		$this->assertEquals( 200, $cancelPayment['status_code'] );
	}

	public function testCancelPaymentFailed(): void {
		$this->expectException( ApiException::class );
		$this->expectExceptionMessage( 'Response Error(403) {"code":3003,"message":"Merchant has no authorization to use this API"}' );

		$gatewayTxnId = "PAID-GATEWAY-TXN-ID";

		$mockResponse = $this->prepareMockResponse( 'cancel-payment-fail-paid-txn.response', 403 );
		$this->curlWrapper->expects( $this->once() )
			->method( 'execute' )
			->with(
				$this->equalTo( 'http://example.com/payments/' . $gatewayTxnId . '/cancel' ), // url
				$this->equalTo( 'POST' ), // method
				$this->anything()
			)->willReturn( $mockResponse );
		$cancelPayment = $this->api->cancelPayment( $gatewayTxnId );
		$this->assertEquals( 403, $cancelPayment['status_code'] );
	}

	public function testCancelPaymentThrowsException(): void {
		$this->expectException( ApiException::class );
		$this->expectExceptionMessage( 'Response Error(404) {"code":4000,"message":"Payment not found"}' );

		$gatewayTxnId = "INVALID-GATEWAY-TXN-ID";

		$mockResponse = $this->prepareMockResponse( 'cancel-payment-fail-invalid-txn-id.response', 404 );
		$this->curlWrapper->expects( $this->once() )
			->method( 'execute' )
			->with(
				$this->equalTo( 'http://example.com/payments/' . $gatewayTxnId . '/cancel' ), // url
				$this->equalTo( 'POST' ), // method
				$this->anything()
			)->willReturn( $mockResponse );

		$this->api->cancelPayment( $gatewayTxnId );
	}

	/**
	 * This helper method is an alternative to Tests/BaseSmashPigUnitTestCase.php:setUpResponse(),
	 * which returns the mock response instead of setting it, inside the method.
	 *
	 * The header size counting code is a bit confusing, I forget what it does every time I see it, but
	 * all that it's doing is telling CurlWrapper::parseResponse() where the headers end and the body begins.
	 *
	 * @param string $filename
	 * @param int $statusCode
	 * @return array
	 */
	private function prepareMockResponse( string $filename, int $statusCode ): array {
		$filePath = __DIR__ . '/../Data/' . $filename;
		$fileContents = file_get_contents( $filePath );

		// the +2 here is to include the two line ending chars "\n\n" in the header count. see doc-bloc for more.
		$header_size = strpos( $fileContents, "\n\n" ) + 2;

		return CurlWrapper::parseResponse(
			$fileContents,
			[
				'http_code' => $statusCode,
				'header_size' => $header_size,
			]
		);
	}

	private function getAuthorizePaymentRequestParams(): array {
		return [
			'params' => [
				'payment_token' => "CV-124c18a5-874d-4982-89d7-b9c256e647b5",
				'order_id' => '123.3',
				'amount' => '100',
				'currency' => 'MXN',
				'country' => 'MX',
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
				'user_ip' => '127.0.0.1'
			],
			'transformedParams' => [
				'amount' => '100',
				'currency' => 'MXN',
				'country' => 'MX',
				'order_id' => '123.3',
				'payment_method_flow' => Api::PAYMENT_METHOD_FLOW_DIRECT,
				'payer' => [
					'name' => 'Lorem Ipsum',
					'email' => 'li@mail.com',
					'document' => '12345',
					'user_reference' => '12345',
					'ip' => '127.0.0.1',
				],
				'callback_url' => 'http://example.com',
				'notification_url' => 'http://example.com',
				'address' => [
					'state' => 'lore',
					'city' => 'lore',
					'zip_code' => 'lore',
					'street' => 'lore',
					'number' => 2,
				],
				'payment_method_id' => Api::PAYMENT_METHOD_ID_CARD,
				'card' => [
					'token' => 'CV-124c18a5-874d-4982-89d7-b9c256e647b5',
					'capture' => false
				],
			]
		];
	}

	private function getRedirectPaymentRequestParams(): array {
		return [
			'params' => [
				'order_id' => '123.3',
				'amount' => '100',
				'currency' => 'MXN',
				'country' => 'MX',
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
				'user_ip' => '127.0.0.1'
			],
			'transformedParams' => [
				'amount' => '100',
				'currency' => 'MXN',
				'country' => 'MX',
				'order_id' => '123.3',
				'payment_method_flow' => API::PAYMENT_METHOD_FLOW_REDIRECT,
				'payer' => [
					'name' => 'Lorem Ipsum',
					'email' => 'li@mail.com',
					'document' => '12345',
					'user_reference' => '12345',
					'ip' => '127.0.0.1',
				],
				'callback_url' => 'http://example.com',
				'notification_url' => 'http://example.com',
				'address' => [
					'state' => 'lore',
					'city' => 'lore',
					'zip_code' => 'lore',
					'street' => 'lore',
					'number' => 2,
				]
			]
		];
	}
}
