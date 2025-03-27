<?php
namespace SmashPig\PaymentProviders\Amazon\Tests;

use ReflectionClass;
use SmashPig\Core\Context;
use SmashPig\Tests\BaseSmashPigUnitTestCase;

class AmazonTestCase extends BaseSmashPigUnitTestCase {

	protected $mockClient;

	public function setUp(): void {
		parent::setUp();
		chdir( __DIR__ ); // So the mock client can find its response files
		$ctx = Context::get();
		$config = AmazonTestConfiguration::instance( $ctx->getGlobalConfiguration() );
		$ctx->setProviderConfiguration( $config );
		$this->mockClient = $config->object( 'payments-client', true );
		$this->mockClient->calls = [];
		$this->mockClient->returns = [];
		$this->mockClient->exceptions = [];
	}

	public function tearDown(): void {
		parent::tearDown();
		$api = new ReflectionClass( 'SmashPig\PaymentProviders\Amazon\AmazonApi' );
		$instance = $api->getProperty( 'instance' );
		$instance->setAccessible( true );
		$instance->setValue( null );
	}
}
