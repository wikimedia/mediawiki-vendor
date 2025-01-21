<?php
namespace SmashPig\PaymentProviders\Gravy;

/**
 * These codes are listed per country here
 * https://docs.dlocal.com/docs/payment-method
 */
class ReferenceData {

	const EW_PAYMENT_METHOD = 'ew';
	const BT_PAYMENT_METHOD = 'bt';
	const DD_PAYMENT_METHOD = 'dd';
	const CC_PAYMENT_METHOD = 'cc';
	const APPLE_PAYMENT_METHOD = 'apple';
	const GOOGLE_PAYMENT_METHOD = 'google';
	const PAYPAL_PAYMENT_METHOD = 'paypal';
	const RTBT_PAYMENT_METHOD = 'rtbt';
	const VENMO_PAYMENT_METHOD = 'venmo';
	const STRIPE_PAYMENT_METHOD = 'stripe';
	const OXXO_PAYMENT_METHOD = 'oxxo';
	const CASH_PAYMENT_METHOD = 'cash';

	protected static $methods = [
		"afterpay" => '',
		'alipay' => self::EW_PAYMENT_METHOD,
		'alipayhk' => self::EW_PAYMENT_METHOD,
		'applepay' => self::APPLE_PAYMENT_METHOD,
		'bacs' => self::DD_PAYMENT_METHOD,
		"banked" => self::BT_PAYMENT_METHOD,
		"becs" => self::DD_PAYMENT_METHOD,
		// Crypto payment service
		"bitpay" => '',
		"boleto" => self::CASH_PAYMENT_METHOD,
		"boost" => '',
		"card" => self::CC_PAYMENT_METHOD,
		"amex" => self::CC_PAYMENT_METHOD,
		"bancontact" => self::CC_PAYMENT_METHOD,
		"carte-bancaire" => self::CC_PAYMENT_METHOD,
		"cirrus" => self::CC_PAYMENT_METHOD,
		"culiance" => self::CC_PAYMENT_METHOD,
		"dankort" => self::CC_PAYMENT_METHOD,
		"diners-club" => self::CC_PAYMENT_METHOD,
		"discover" => self::CC_PAYMENT_METHOD,
		"eftpos-australia" => self::CC_PAYMENT_METHOD,
		"elo" => self::CC_PAYMENT_METHOD,
		"hipercard" => self::CC_PAYMENT_METHOD,
		"jcb" => self::CC_PAYMENT_METHOD,
		"maestro" => self::CC_PAYMENT_METHOD,
		"mastercard" => self::CC_PAYMENT_METHOD,
		"mir" => self::CC_PAYMENT_METHOD,
		"nyce" => self::CC_PAYMENT_METHOD,
		"other" => self::CC_PAYMENT_METHOD,
		"pulse" => self::CC_PAYMENT_METHOD,
		"rupay" => self::CC_PAYMENT_METHOD,
		"star" => self::CC_PAYMENT_METHOD,
		"uatp" => self::CC_PAYMENT_METHOD,
		"unionpay" => self::CC_PAYMENT_METHOD,
		"visa" => self::CC_PAYMENT_METHOD,
		"cashapp" => '',
		"chaseorbital" => '',
		"checkout-session" => '',
		"clearpay" => '',
		"click-to-pay" => '',
		"dana" => '',
		"dcb" => '',
		"dlocal" => '',
		"ebanx" => '',
		"everydaypay" => '',
		"gcash" => '',
		"giropay" => '',
		"gocardless" => '',
		"googlepay" => self::GOOGLE_PAYMENT_METHOD,
		"gopay" => '',
		"grabpay" => '',
		"ideal" => self::RTBT_PAYMENT_METHOD,
		"kakaopay" => '',
		"klarna" => '',
		"laybuy" => '',
		"linkaja" => '',
		"maybankqrpay" => '',
		"multibanco" => self::RTBT_PAYMENT_METHOD,
		"oney_3x" => '',
		"oney_4x" => '',
		"oney_6x" => '',
		"oney_10x" => '',
		"oney_12x" => '',
		"ovo" => '',
		"oxxo" => self::OXXO_PAYMENT_METHOD,
		"payid" => '',
		"paymaya" => '',
		"paypal" => self::PAYPAL_PAYMENT_METHOD,
		"paypalpaylater" => self::PAYPAL_PAYMENT_METHOD,
		"payto" => '',
		"venmo" => self::VENMO_PAYMENT_METHOD,
		"pix" => self::BT_PAYMENT_METHOD,
		"rabbitlinepay" => '',
		"scalapay" => '',
		"sepa" => self::RTBT_PAYMENT_METHOD,
		"shopeepay" => '',
		"singteldash" => '',
		"sofort" => self::RTBT_PAYMENT_METHOD,
		"stripedd" => self::STRIPE_PAYMENT_METHOD,
		"thaiqr" => '',
		"touchngo" => '',
		"truemoney" => '',
		"trustly" => self::DD_PAYMENT_METHOD,
		"trustlyus" => self::DD_PAYMENT_METHOD,
		"trustlyeurope" => self::DD_PAYMENT_METHOD,
		"network-token" => '',
		"givingblock" => '',
		"wechat" => '',
		"zippay" => '',
		"eps" => self::RTBT_PAYMENT_METHOD,
		"linepay" => '',
		"razorpay" => '',
		"multipago" => '',
		"waave" => '',
		"smartpay" => '',
		"vipps" => ""
	];

	// At least one dLocal bank code is used for both credit cards
	// and bank transfers. We have a different internal code for each.
	protected static $cardPaymentSubmethods = [
		"amex" => 'amex',
		"bancontact" => '',
		"carte-bancaire" => 'cb',
		"cirrus" => '',
		"culiance" => '',
		"dankort" => '',
		"diners-club" => 'diners',
		"discover" => 'discover',
		"eftpos-australia" => '',
		"elo" => 'elo',
		"hipercard" => 'hipercard',
		"jcb" => 'jcb',
		"maestro" => 'maestro',
		"mastercard" => 'mc',
		"mir" => '',
		"nyce" => '',
		"other" => '',
		"pulse" => '',
		"rupay" => 'rupay',
		"star" => '',
		"uatp" => '',
		"unionpay" => '',
		"visa" => 'visa',
	];

	protected static $ewSubmethods = [
		'alipay' => 'ew_alipay',
		'alipayhk' => 'ew_alipay',
	];

	protected static $rtbtSubmethods = [
		"ideal" => "rtbt",
		"multibanco" => "rtbt",
		"sepa" => "rtbt",
		"sofort" => "rtbt",
		"eps" => "rtbt",
	];

	protected static $ddSubmethods = [
		"trustly" => "ach",
		"trustlyus" => "ach",
		"trustlyeurope" => ''
	];

	public static function decodePaymentMethod( string $method, ?string $scheme = '' ): array {
		$methods = self::$methods;
		$payment_method = $methods[$method] ?? '';
		$payment_submethod = '';

		switch ( $payment_method ) {
			case self::EW_PAYMENT_METHOD:
				$payment_submethod = self::$ewSubmethods[$scheme];
				break;
			case self::RTBT_PAYMENT_METHOD:
				$payment_submethod = self::$rtbtSubmethods[$scheme];
				break;
			case self::CC_PAYMENT_METHOD:
			case self::APPLE_PAYMENT_METHOD:
			case self::GOOGLE_PAYMENT_METHOD:
			$payment_submethod = self::$cardPaymentSubmethods[$scheme];
				break;
			case self::DD_PAYMENT_METHOD:
				$payment_submethod = self::$ddSubmethods[$method];
				break;
			default:
				break;
		}

		return [ $payment_method, $payment_submethod ];
	}
}
