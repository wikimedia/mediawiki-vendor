<?php

namespace SmashPig\PaymentProviders\Gravy\Tests;

use Gr4vy\Gr4vyConfig;
use PHPUnit\Framework\MockObject\Exception;
use SmashPig\Core\Context;
use SmashPig\Core\ProviderConfiguration;
use SmashPig\PaymentProviders\Gravy\Api;
use SmashPig\PaymentProviders\Gravy\GravySDKWrapper;
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
	 * Inject a GravySDKWrapper into Api::$gravyApiClient.
	 *
	 * - If you pass a GravySDKWrapper, it will be used directly.
	 * - If you pass a Gr4vyConfig mock, it will be wrapped in a real GravySDKWrapper so
	 *   wrapper error handling/timing logic still runs.
	 *
	 * @param Gr4vyConfig|GravySDKWrapper|null $mockGravyClient
	 * @param Api|null $api
	 * @return void
	 * @throws \ReflectionException
	 */
	protected function setMockGravyClient( $mockGravyClient = null, $api = null ): void {
		if ( $api === null ) {
			$api = $this->createApiInstance();
		}
		if ( $mockGravyClient === null ) {
			$mockGravyClient = $this->createMock( Gr4vyConfig::class );
		}

		$wrapper = $mockGravyClient instanceof GravySDKWrapper
			? $mockGravyClient
			: new GravySDKWrapper( $mockGravyClient );

		$reflection = new \ReflectionClass( $api );
		$property = $reflection->getProperty( 'gravyApiClient' );
		$property->setValue( $api, $wrapper );

		// Replace the provider's API with our modified one
		$providerReflection = new \ReflectionClass( $this->provider );
		$apiProperty = $providerReflection->getProperty( 'api' );
		$apiProperty->setValue( $this->provider, $api );
	}
}
