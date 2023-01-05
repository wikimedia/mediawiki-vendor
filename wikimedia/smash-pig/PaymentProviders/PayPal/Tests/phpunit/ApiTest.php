<?php

namespace SmashPig\PaymentProviders\PayPal\Tests\phpunit;

use SmashPig\PaymentProviders\PayPal\Api;
use SmashPig\Tests\BaseSmashPigUnitTestCase;

class ApiTest extends BaseSmashPigUnitTestCase {
	/**
	 * @var Api
	 */
	protected $api;

	public function setUp() : void {
		parent::setUp();
		$providerConfiguration = $this->setProviderConfiguration( 'paypal' );
		$this->curlWrapper = $this->createMock( '\SmashPig\Core\Http\CurlWrapper' );
		$providerConfiguration->overrideObjectInstance( 'curl/wrapper', $this->curlWrapper );
		$this->api = new Api( [
			'endpoint' => 'https://testurl.com',
			'user' => 'test_user@paypal.com',
			'password' => 'test_password',
			'certificate_path' => '/path/to/cert',
			'version' => '204',
		] );
	}

	public function testSampleApiCall() {
		// set up the expectations
		$expectedRequestBody = [
			'USER' => "test_user@paypal.com",
			'PWD' => "test_password",
			'VERSION' => 204,
			'METHOD' => "GetExpressCheckoutDetails",
			'TOKEN' => "EC-TESTTOKEN12345678910"
		];

		$stringifiedExpectedRequestBody = http_build_query( $expectedRequestBody );

		$expectedHeaders = [
			'Content-Length' => strlen( $stringifiedExpectedRequestBody )
		];

		$testApiResponse = $this->getTestData( 'GetExpressCheckoutDetails.response' );

		$this->curlWrapper->expects( $this->once() )
			->method( 'execute' )
			->with(
				$this->equalTo( 'https://testurl.com' ),
				$this->equalTo( 'POST' ),
				$this->equalTo( $expectedHeaders ),
				$this->equalTo( $stringifiedExpectedRequestBody )
			)->willReturn( [
				'status' => 200,
				'body' => $testApiResponse,
		] );

		$testApiParams = [
				'METHOD' => 'GetExpressCheckoutDetails',
				'TOKEN' => 'EC-TESTTOKEN12345678910'
		];

		// call the code
		$response = $this->api->makeApiCall( $testApiParams );

		// check the results
		$this->assertEquals( http_build_query( $response ), $testApiResponse );
	}

	private function getTestData( $testFileName ) {
		$testFileDir = __DIR__ . '/../Data/';
		$testFilePath = $testFileDir . $testFileName;
		if ( file_exists( $testFilePath ) ) {
			return file_get_contents( $testFilePath );
		}
	}
}
