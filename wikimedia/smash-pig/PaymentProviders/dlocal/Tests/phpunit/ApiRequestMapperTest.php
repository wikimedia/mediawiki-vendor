<?php

namespace SmashPig\PaymentProviders\dlocal\Tests\phpunit;

use PHPUnit\Framework\TestCase;
use SmashPig\PaymentProviders\dlocal\ApiMappers\ApiRequestMapper;

class ApiRequestMapperTest extends TestCase {

	public function testInitializeApiRequestMapper(): void {
		$class = new ApiRequestMapper();
		$this->assertInstanceOf( ApiRequestMapper::class, $class );
	}

}
