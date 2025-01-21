<?php
namespace SmashPig\PaymentProviders\Fundraiseup\Tests;

use SmashPig\Core\GlobalConfiguration;
use SmashPig\Tests\TestingProviderConfiguration;

class FundraiseupTestConfiguration extends TestingProviderConfiguration {
	public static function instance( GlobalConfiguration $globalConfig ) {
		return self::createForProvider(
			'fundraiseup',
			$globalConfig
		);
	}
}
