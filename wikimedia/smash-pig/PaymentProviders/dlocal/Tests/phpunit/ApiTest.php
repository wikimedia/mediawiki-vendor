<?php

namespace SmashPig\PaymentProviders\dlocal\Tests;

use PHPUnit\Framework\TestCase;
use SmashPig\PaymentProviders\dlocal\Api;

class ApiTest extends TestCase {

	public function testCanInstantiateApi(): void {
		$params = [
			'endpoint' => 'http://example.com',
			'login' => 'test_login',
			'trans_key' => 'test_dg$3434534E',
			'secret' => 'test_ITSASECRET'
		];

		$Api = new Api( $params );
		$this->assertInstanceOf( \SmashPig\PaymentProviders\dlocal\Api::class, $Api );
	}

}
