<?php

namespace SmashPig\PaymentProviders\dlocal\Tests;

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
		$mockResponse = $this->prepareMockResponse( 'authorize-payment.response', 200 );
		$this->curlWrapper->expects( $this->once() )
				->method( 'execute' )
				->with(
						$this->equalTo( 'http://example.com/payments' ),
						$this->equalTo( 'POST' )
				)->willReturn( $mockResponse );

		$params = [
			"amount" => 120,
			"currency" => "USD",
			"country" => "BR",
			"payment_method_id" => "CARD",
			"payment_method_flow" => "DIRECT",
			"payer" => [
				"name" => "Thiago Gabriel",
				"email" => "thiago@example.com",
				"document" => "53033315550",
				"user_reference" => "12345",
				"address" => [
					"state"  => "Rio de Janeiro",
					"city" => "Volta Redonda",
					"zip_code" => "27275-595",
					"street" => "Servidao B-1",
					"number" => "1106"
				],
				"ip" => "127.0.0.1",
				],
			"card" => [
				"token" => "CV-124c18a5-874d-4982-89d7-b9c256e647b5"
			],
			"order_id" => "657434343",
		];
		$results = $this->api->authorizePayment( $params );

		$expectedAuthorizePaymentResult = [
				"id" => "D-4-80ca7fbd-67ad-444a-aa88-791ca4a0c2b2",
				"amount" => 120,
				"currency" => "USD",
				"country" => "BR",
				"payment_method_id" => "CARD",
				"payment_method_type" => "CARD",
				"payment_method_flow" => "DIRECT",
				"card" => [
						"holder_name" => "Thiago Gabriel",
						"expiration_month" => 10,
						"expiration_year" => 2040,
						"brand" => "VI",
						"last4" => "1111"
						],
				"created_date" => "2018-12-26T20:28:47.000+0000",
				"approved_date" => "2018-12-26T20:28:47.000+0000",
				"status" => "AUTHORIZED",
				"status_detail" => "The payment was authorized",
				"status_code" => "600",
				"order_id" => "657434343",
				"notification_url" => "http://merchant.com/notifications"
		];

		$this->assertEquals( $expectedAuthorizePaymentResult, $results );
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
}
