<?php

namespace SmashPig\PaymentProviders\Gravy\Tests;

use Gr4vy\Gr4vyConfig;
use PHPUnit\Framework\MockObject\Exception;
use SmashPig\Core\Context;
use SmashPig\Core\ProviderConfiguration;
use SmashPig\PaymentProviders\Gravy\Api;
use SmashPig\Tests\BaseSmashPigUnitTestCase;

class BaseGravyTestCase extends BaseSmashPigUnitTestCase {

	/**
	 * @var ProviderConfiguration
	 */
	public $config;

	/**
	 * @var \PHPUnit_Framework_MockObject_MockObject
	 */
	protected $mockApi;

	/**
	 * @var \SmashPig\PaymentProviders\Gravy\PaymentProvider
	 */
	public $provider;

	/**
	 * @throws Exception
	 */
	public function setUp(): void {
		parent::setUp();
		$this->mockApi = $this->createMock( 'SmashPig\PaymentProviders\Gravy\Api' );
		$ctx = Context::get();
		$this->config = GravyTestConfiguration::instance( $this->mockApi, $ctx->getGlobalConfiguration() );
		$ctx->setProviderConfiguration( $this->config );
	}

	protected function getCreateDonorParams(): array {
		$params = [];
		$params['first_name'] = 'Lorem';
		$params['last_name'] = 'Ipsum';
		$params['email'] = 'lorem@ipsum';
		$params['street_address'] = '10 hopewell street';
		$params['postal_code'] = '1234';
		$params['country'] = 'US';
		$params['employer'] = 'Wikimedia Foundation';

		return $params;
	}

	/**
	 * @return Api
	 */
	protected function createApiInstance(): Api {
		return new Api();
	}

	/**
	 * @param null $mockGravyClient
	 * @param null $api
	 * @return void
	 * @throws Exception
	 * @throws \ReflectionException
	 */
	protected function setMockGravyClient( $mockGravyClient = null, $api = null ): void {
		if ( $api === null ) {
			$api = $this->createApiInstance();
		}
		if ( $mockGravyClient === null ) {
			$mockGravyClient = $this->createMock( Gr4vyConfig::class );
		}
		$reflection = new \ReflectionClass( $api );
		$property = $reflection->getProperty( 'gravyApiClient' );
		$property->setValue( $api, $mockGravyClient );

		// Replace the provider's API with our modified one
		$providerReflection = new \ReflectionClass( $this->provider );
		$apiProperty = $providerReflection->getProperty( 'api' );
		$apiProperty->setValue( $this->provider, $api );
	}
}
