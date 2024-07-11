<?php

namespace SmashPig\PaymentProviders\Gravy\Tests;

use SmashPig\Core\Context;
use SmashPig\Core\ProviderConfiguration;
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

	public function setUp() : void {
		parent::setUp();
		$this->mockApi = $this->createMock( 'SmashPig\PaymentProviders\Gravy\Api' );
		$ctx = Context::get();
		$this->config = GravyTestConfiguration::instance( $this->mockApi, $ctx->getGlobalConfiguration() );
		$ctx->setProviderConfiguration( $this->config );
	}
}
