<?php

namespace SmashPig\PaymentProviders\Amazon\Tests;

use SmashPig\Core\GlobalConfiguration;
use SmashPig\Tests\TestingProviderConfiguration;

class AmazonTestConfiguration extends TestingProviderConfiguration {

	public static function instance( GlobalConfiguration $globalConfig ) {
		return self::createForProviderWithOverrideFile(
			'amazon',
			__DIR__ . '/config_test.yaml',
			$globalConfig
		);
	}
}
