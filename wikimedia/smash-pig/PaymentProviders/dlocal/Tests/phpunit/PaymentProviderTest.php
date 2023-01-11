<?php

namespace SmashPig\PaymentProviders\dlocal\Tests;

use PHPUnit\Framework\TestCase;
use SmashPig\PaymentProviders\dlocal\PaymentProvider;

class PaymentProviderTest extends TestCase {

	public function testCanInstantiatePaymentProvider(): void {
		$PaymentProvider = new PaymentProvider();
		$this->assertInstanceOf( \SmashPig\PaymentProviders\dlocal\PaymentProvider::class, $PaymentProvider );
	}

}
