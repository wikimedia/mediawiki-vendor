<?php
namespace SmashPig\PaymentProviders\Adyen\Tests;

use PHPUnit\Framework\TestCase;
use SmashPig\PaymentProviders\Adyen\ReferenceData;

class ReferenceDataTest extends TestCase {
	public function testParseApplePay() {
		// In the audit files we see the same value under 'Payment Method' and 'Payment Method Variant'
		// field for Apple Pay cards
		[ $method, $submethod ] = ReferenceData::decodePaymentMethod( 'visa_applepay', 'visa_applepay' );
		$this->assertEquals( 'apple', $method );
		$this->assertEquals( 'visa', $submethod );
	}

	public function testParseApplePayElectron() {
		[ $method, $submethod ] = ReferenceData::decodePaymentMethod( 'electron_applepay', 'electron_applepay' );
		$this->assertEquals( 'apple', $method );
		$this->assertEquals( 'visa-electron', $submethod );
	}

	public function testParseGooglePay() {
		// In the audit files we see the same value under 'Payment Method' and 'Payment Method Variant'
		// field for Google Pay as well
		[ $method, $submethod ] = ReferenceData::decodePaymentMethod( 'visa_googlepay', 'visa_googlepay' );
		$this->assertEquals( 'google', $method );
		$this->assertEquals( 'visa', $submethod );
	}

	public function testParseApplePayPulse() {
		[ $method, $submethod ] = ReferenceData::decodePaymentMethod( 'pulse', 'visa_applepay' );
		$this->assertEquals( 'apple', $method );
		$this->assertEquals( 'visa', $submethod );
	}
}
