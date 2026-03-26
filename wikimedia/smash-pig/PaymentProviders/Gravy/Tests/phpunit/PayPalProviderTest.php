<?php

namespace SmashPig\PaymentProviders\Gravy\Tests\phpunit;

use SmashPig\PaymentProviders\Gravy\PaypalPaymentProvider;
use SmashPig\PaymentProviders\Gravy\Tests\BaseGravyTestCase;

/**
 * @group Gravy
 */
class PayPalProviderTest extends BaseGravyTestCase {
	/**
	 * @var PaypalPaymentProvider
	 */
	public $provider;

	public function setUp(): void {
		parent::setUp();
		$this->provider = $this->config->object( 'payment-provider/paypal' );
	}

	public function testGetSuccessfulPaymentServiceDefinitionRequest() {
		$responseBody = json_decode( file_get_contents( __DIR__ . '/../Data/payment-service-definition-successful.json' ), true );
		$params = [
			'method' => 'paypal-paypal'
		];
		$this->mockApi->expects( $this->once() )
			->method( 'getPaymentServiceDefinition' )
			->with( $params['method'] )
			->willReturn( $responseBody );

		$response = $this->provider->getPaymentServiceDefinition();

		$this->assertInstanceOf( '\SmashPig\PaymentProviders\Responses\PaymentMethodResponse',
			$response );
		$this->assertEquals( $responseBody['supported_countries'], $response->getSupportedCountries() );
		$this->assertEquals( $responseBody['supported_currencies'], $response->getSupportedCurrencies() );
		$this->assertTrue( $response->isSuccessful() );
	}

	public function testCachePaymentMethodSuccessfulResponse() {
		$responseBody = json_decode( file_get_contents( __DIR__ . '/../Data/payment-service-definition-successful.json' ), true );
		$params = [
			'method' => 'paypal-paypal'
		];
		$this->mockApi->expects( $this->once() )
			->method( 'getPaymentServiceDefinition' )
			->with( $params['method'] )
			->willReturn( $responseBody );

		$response = $this->provider->getPaymentServiceDefinition();

		$this->assertTrue( $response->isSuccessful() );
		$this->assertEquals( $responseBody['supported_countries'], $response->getSupportedCountries() );

		$cachedResults = $this->provider->getPaymentServiceDefinition();

		$this->assertEquals( $response->getSupportedCountries(), $cachedResults->getSupportedCountries() );
	}

	/**
	 * When the lookup returns 404 we should cache the error
	 */
	public function testCachePaymentMethodErrorResponse() {
		$responseBody = json_decode( file_get_contents( __DIR__ . '/../Data/payment-service-definition-error.json' ), true );
		$params = [
			'method' => 'paypal-paypal'
		];
		$this->mockApi->expects( $this->once() )
			->method( 'getPaymentServiceDefinition' )
			->with( $params['method'] )
			->willReturn( $responseBody );

		$response = $this->provider->getPaymentServiceDefinition();

		$this->assertTrue( !$response->isSuccessful() );
		$this->assertEquals( [], $response->getSupportedCountries() );

		$cachedResults = $this->provider->getPaymentServiceDefinition();

		$this->assertEquals( [], $cachedResults->getSupportedCountries() );
	}
}
