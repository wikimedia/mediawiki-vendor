<?php
namespace SmashPig\PaymentProviders\Adyen\Tests;

use PHPUnit\Framework\TestCase;
use SmashPig\PaymentProviders\Adyen\ReferenceData;

class ReferenceDataTest extends TestCase {

	/**
	 * @dataProvider paymentMethodProvider
	 */
	public function testDecodePaymentMethod( string $variant, array $expected ) {
		[ $method, $submethod ] = ReferenceData::decodePaymentMethod( $variant, $variant );

		$this->assertSame( $expected['payment_method'], $method );
		$this->assertSame( $expected['submethod'], $submethod );
	}

	public static function paymentMethodProvider(): array {
		return [
			'ach' => [ 'ach', [ 'payment_method' => 'dd', 'submethod' => 'ach' ] ],
			'alipay' => [ 'alipay', [ 'payment_method' => 'ew', 'submethod' => 'ew_alipay' ] ],
			'amex' => [ 'amex', [ 'payment_method' => 'cc', 'submethod' => 'amex' ] ],
			'amex_applepay' => [ 'amex_applepay', [ 'payment_method' => 'apple', 'submethod' => 'amex' ] ],
			'amex_googlepay' => [ 'amex_googlepay', [ 'payment_method' => 'google', 'submethod' => 'amex' ] ],
			'applepay' => [ 'applepay', [ 'payment_method' => 'apple', 'submethod' => 'apple' ] ],
			'bijcard' => [ 'bijcard', [ 'payment_method' => 'cc', 'submethod' => 'bij' ] ],
			'banktransfer_IBAN' => [ 'banktransfer_IBAN', [ 'payment_method' => 'bt', 'submethod' => 'iban' ] ],
			'cartebancaire' => [ 'cartebancaire', [ 'payment_method' => 'cc', 'submethod' => 'cb' ] ],
			'cartebancaire_applepay' => [ 'cartebancaire_applepay', [ 'payment_method' => 'apple', 'submethod' => 'cb' ] ],
			'cup' => [ 'cup', [ 'payment_method' => 'cc', 'submethod' => 'cup' ] ],
			'diners' => [ 'diners', [ 'payment_method' => 'cc', 'submethod' => 'diners' ] ],
			'directEbanking' => [ 'directEbanking', [ 'payment_method' => 'rtbt', 'submethod' => 'rtbt_sofortuberweisung' ] ],
			'discover' => [ 'discover', [ 'payment_method' => 'cc', 'submethod' => 'discover' ] ],
			'discover_applepay' => [ 'discover_applepay', [ 'payment_method' => 'apple', 'submethod' => 'discover' ] ],
			'discover_googlepay' => [ 'discover_googlepay', [ 'payment_method' => 'google', 'submethod' => 'discover' ] ],
			'dotpay' => [ 'dotpay', [ 'payment_method' => 'ew', 'submethod' => 'ew_dotpay' ] ],
			'electron_applepay' => [ 'electron_applepay', [ 'payment_method' => 'apple', 'submethod' => 'visa-electron' ] ],
			'electron_googlepay' => [ 'electron_googlepay', [ 'payment_method' => 'google', 'submethod' => 'visa-electron' ] ],
			'eftpos_australia' => [ 'eftpos_australia', [ 'payment_method' => 'cc', 'submethod' => 'mc' ] ],
			'googlepay' => [ 'googlepay', [ 'payment_method' => 'google', 'submethod' => 'google' ] ],
			'googlewallet' => [ 'googlewallet', [ 'payment_method' => 'google', 'submethod' => 'google' ] ],
			'ideal' => [ 'ideal', [ 'payment_method' => 'rtbt', 'submethod' => 'rtbt_ideal' ] ],
			'interlink' => [ 'interlink', [ 'payment_method' => 'cc', 'submethod' => 'visa' ] ],
			'jcb' => [ 'jcb', [ 'payment_method' => 'cc', 'submethod' => 'jcb' ] ],
			'jcb_applepay' => [ 'jcb_applepay', [ 'payment_method' => 'apple', 'submethod' => 'jcb' ] ],
			'jcbprepaidanonymous' => [ 'jcbprepaidanonymous', [ 'payment_method' => 'cc', 'submethod' => 'jcb' ] ],
			'mc' => [ 'mc', [ 'payment_method' => 'cc', 'submethod' => 'mc' ] ],
			'mc_applepay' => [ 'mc_applepay', [ 'payment_method' => 'apple', 'submethod' => 'mc' ] ],
			'mc_googlepay' => [ 'mc_googlepay', [ 'payment_method' => 'google', 'submethod' => 'mc' ] ],
			'mc_vipps' => [ 'mc_vipps', [ 'payment_method' => 'vipps', 'submethod' => 'mc' ] ],
			'maestro' => [ 'maestro', [ 'payment_method' => 'cc', 'submethod' => 'maestro' ] ],
			'maestro_googlepay' => [ 'maestro_googlepay', [ 'payment_method' => 'google', 'submethod' => 'maestro' ] ],
			'multibanco' => [ 'multibanco', [ 'payment_method' => 'rtbt', 'submethod' => 'rtbt_multibanco' ] ],
			'nyce' => [ 'nyce', [ 'payment_method' => 'cc', 'submethod' => 'mc' ] ],
			'onlineBanking_CZ' => [ 'onlineBanking_CZ', [ 'payment_method' => 'bt', 'submethod' => '' ] ],
			'pulse' => [ 'pulse', [ 'payment_method' => 'cc', 'submethod' => 'visa' ] ],
			'safetypay' => [ 'safetypay', [ 'payment_method' => 'rtbt', 'submethod' => 'rtbt_safetypay' ] ],
			'sepadirectdebit' => [ 'sepadirectdebit', [ 'payment_method' => 'rtbt', 'submethod' => 'sepadirectdebit' ] ],
			'star' => [ 'star', [ 'payment_method' => 'cc', 'submethod' => 'visa' ] ],
			'tenpay' => [ 'tenpay', [ 'payment_method' => 'ew', 'submethod' => 'ew_tenpay' ] ],
			'trustly' => [ 'trustly', [ 'payment_method' => 'obt', 'submethod' => 'trustly' ] ],
			'vipps' => [ 'vipps', [ 'payment_method' => 'ew', 'submethod' => 'vipps' ] ],
			'visa' => [ 'visa', [ 'payment_method' => 'cc', 'submethod' => 'visa' ] ],
			'visa_applepay' => [ 'visa_applepay', [ 'payment_method' => 'apple', 'submethod' => 'visa' ] ],
			'visadebit_applepay' => [ 'visadebit_applepay', [ 'payment_method' => 'apple', 'submethod' => 'visa' ] ],
			'visa_googlepay' => [ 'visa_googlepay', [ 'payment_method' => 'google', 'submethod' => 'visa' ] ],
			'vpay' => [ 'vpay', [ 'payment_method' => 'cc', 'submethod' => 'visa-debit' ] ],
			'visadankort' => [ 'visadankort', [ 'payment_method' => 'cc', 'submethod' => 'visa' ] ],
		];
	}

}
