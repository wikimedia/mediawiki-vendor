<?php

namespace SmashPig\PaymentProviders\Gravy\Tests\phpunit;

use SmashPig\PaymentData\PaymentMethod;
use SmashPig\PaymentProviders\Gravy\ReferenceData;
use SmashPig\Tests\BaseSmashPigUnitTestCase;

class GravyReferenceDataTest extends BaseSmashPigUnitTestCase {

	/**
	 * General sanity checks for common card schemes via 'card' method
	 */
	public function testDecodeCommonCardSchemes(): void {
		[ $paymentMethodVisa, $paymentSubmethodVisa ] = ReferenceData::decodePaymentMethod( 'card', 'visa' );
		$this->assertEquals( PaymentMethod::CC, $paymentMethodVisa );
		$this->assertEquals( 'visa', $paymentSubmethodVisa );

		[ $paymentMethodAmex, $paymentSubmethodAmex ] = ReferenceData::decodePaymentMethod( 'card', 'amex' );
		$this->assertEquals( PaymentMethod::CC, $paymentMethodAmex );
		$this->assertEquals( 'amex', $paymentSubmethodAmex );

		[ $paymentMethodMastercard, $paymentSubmethodMastercard ] = ReferenceData::decodePaymentMethod( 'card', 'mastercard' );
		$this->assertEquals( PaymentMethod::CC, $paymentMethodMastercard );
		$this->assertEquals( 'mc', $paymentSubmethodMastercard );
	}

	/**
	 * Apple and Google Pay should use card scheme mapping for submethods
	 */
	public function testDecodeAppleAndGooglePay(): void {
		[ $paymentMethodApple, $paymentSubmethodApple ] = ReferenceData::decodePaymentMethod( 'applepay', 'visa' );
		$this->assertEquals( PaymentMethod::APPLE, $paymentMethodApple );
		$this->assertEquals( 'visa', $paymentSubmethodApple );

		[ $paymentMethodGoogle, $paymentSubmethodGoogle ] = ReferenceData::decodePaymentMethod( 'googlepay', 'mastercard' );
		$this->assertEquals( PaymentMethod::GOOGLE, $paymentMethodGoogle );
		$this->assertEquals( 'mc', $paymentSubmethodGoogle );
	}

	public function testDecodeDirectDebitTrustlyVariants(): void {
		[ $paymentMethodTrustly, $paymentSubmethodTrustly ] = ReferenceData::decodePaymentMethod( 'trustly' );
		$this->assertEquals( PaymentMethod::DD, $paymentMethodTrustly );
		$this->assertEquals( 'ach', $paymentSubmethodTrustly );

		[ $paymentMethodTrustlyEurope, $paymentSubmethodTrustlyEurope ] = ReferenceData::decodePaymentMethod( 'trustlyeurope' );
		$this->assertEquals( PaymentMethod::DD, $paymentMethodTrustlyEurope );
		$this->assertSame( '', $paymentSubmethodTrustlyEurope );

		[ $paymentMethodTrustlyUS, $paymentSubmethodTrustlyUS ] = ReferenceData::decodePaymentMethod( 'trustlyus' );
		$this->assertEquals( PaymentMethod::DD, $paymentMethodTrustlyUS );
		$this->assertEquals( 'ach', $paymentSubmethodTrustlyUS );
	}

	public function testGetShorthandPaymentMethodReturnsExpectedValues(): void {
		$this->assertSame( PaymentMethod::GOOGLE, ReferenceData::getShorthandPaymentMethod( 'googlepay_pan_only' ) );
		$this->assertSame( PaymentMethod::PAYPAL, ReferenceData::getShorthandPaymentMethod( 'paypal' ) );
		$this->assertSame( PaymentMethod::BT, ReferenceData::getShorthandPaymentMethod( 'webpay' ) );
	}

	public function testGetShorthandPaymentMethodThrowsOnUnknown(): void {
		$this->expectException( \InvalidArgumentException::class );
		$this->expectExceptionMessage( "Payment method 'made-up-method' not found" );
		ReferenceData::getShorthandPaymentMethod( 'made-up-method' );
	}

	public function testDecodeGooglePayPanOnlyWithScheme(): void {
		$result = ReferenceData::decodePaymentMethod( 'googlepay_pan_only', 'visa' );
		$this->assertEquals( PaymentMethod::GOOGLE, $result[0] );
		$this->assertEquals( 'visa', $result[1] );
	}

	/**
	 * Accel method added in 365b5db... should map to CC and have 'accel' submethod when scheme provided
	 */
	public function testDecodeAccelWithScheme(): void {
		[ $paymentMethodAccel, $paymentSubmethodAccel ] = ReferenceData::decodePaymentMethod( 'accel', 'accel' );
		$this->assertEquals( PaymentMethod::CC, $paymentMethodAccel );
		$this->assertEquals( 'accel', $paymentSubmethodAccel );
	}

	public function testDecodeAccelWithoutScheme(): void {
		[ $paymentMethodAccel, $paymentSubmethodAccel ] = ReferenceData::decodePaymentMethod( 'accel' );
		$this->assertEquals( PaymentMethod::CC, $paymentMethodAccel );
		$this->assertSame( '', $paymentSubmethodAccel );
	}

	public function testGetShorthandAccel(): void {
		$this->assertSame( PaymentMethod::CC, ReferenceData::getShorthandPaymentMethod( 'accel' ) );
	}
}
