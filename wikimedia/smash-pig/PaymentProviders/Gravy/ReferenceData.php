<?php
namespace SmashPig\PaymentProviders\Gravy;

use SmashPig\PaymentData\PaymentMethod;
use SmashPig\PaymentProviders\Gravy\PaymentMethod as PaymentSubmethod;

/**
 * These codes are listed per country here
 * https://docs.dlocal.com/docs/payment-method
 */
class ReferenceData {

	protected static $paymentMethodMapper = [
		'abitab' => PaymentMethod::CASH,
		'afterpay' => '',
		'alipay' => PaymentMethod::EW,
		'alipayhk' => PaymentMethod::EW,
		'amex' => PaymentMethod::CC,
		'applepay' => PaymentMethod::APPLE,
		'bacs' => PaymentMethod::DD,
		'bancomer' => PaymentMethod::BT,
		'bancontact' => PaymentMethod::CC,
		'banked' => PaymentMethod::BT,
		'bcp' => PaymentMethod::BT,
		'becs' => PaymentMethod::DD,
		'bitpay' => '', // Crypto payment service
		'boleto' => PaymentMethod::CASH,
		'boost' => '',
		'card' => PaymentMethod::CC,
		'carte-bancaire' => PaymentMethod::CC,
		'cashapp' => '',
		'chaseorbital' => '',
		'checkout-session' => '',
		'cirrus' => PaymentMethod::CC,
		'clearpay' => '',
		'click-to-pay' => '',
		'culiance' => PaymentMethod::CC,
		'dana' => '',
		'dankort' => PaymentMethod::CC,
		'dcb' => '',
		'diners-club' => PaymentMethod::CC,
		'discover' => PaymentMethod::CC,
		'dlocal' => '',
		'ebanx' => '',
		'eftpos-australia' => PaymentMethod::CC,
		'elo' => PaymentMethod::CC,
		'eps' => PaymentMethod::RTBT,
		'everydaypay' => '',
		'gcash' => '',
		'giropay' => '',
		'givingblock' => '',
		'gocardless' => '',
		'googlepay' => PaymentMethod::GOOGLE,
		'googlepay_pan_only' => PaymentMethod::GOOGLE,
		'gopay' => '',
		'grabpay' => '',
		'hipercard' => PaymentMethod::CC,
		'ideal' => PaymentMethod::RTBT,
		'jcb' => PaymentMethod::CC,
		'kakaopay' => '',
		'klarna' => '',
		'laybuy' => '',
		'linepay' => '',
		'linkaja' => '',
		'maestro' => PaymentMethod::CC,
		'mastercard' => PaymentMethod::CC,
		'maybankqrpay' => '',
		'mir' => PaymentMethod::CC,
		'multibanco' => PaymentMethod::RTBT,
		'multipago' => '',
		'netbanking' => PaymentMethod::BT,
		'network-token' => '',
		'nyce' => PaymentMethod::CC,
		'oney_10x' => '',
		'oney_12x' => '',
		'oney_3x' => '',
		'oney_4x' => '',
		'oney_6x' => '',
		'other' => PaymentMethod::CC,
		'ovo' => '',
		'oxxo' => PaymentMethod::CASH,
		'payid' => '',
		'paymaya' => '',
		'paypal' => PaymentMethod::PAYPAL,
		'paypalpaylater' => PaymentMethod::PAYPAL,
		'payto' => '',
		'pix' => PaymentMethod::CASH,
		'pse' => PaymentMethod::BT,
		'pagoefectivo' => PaymentMethod::CASH,
		'pulse' => PaymentMethod::CC,
		'rabbitlinepay' => '',
		'razorpay' => '',
		'rupay' => PaymentMethod::CC,
		'redpagos' => PaymentMethod::CASH,
		'rapipago' => PaymentMethod::CASH,
		'scalapay' => '',
		'sepa' => PaymentMethod::RTBT,
		'shopeepay' => '',
		'singteldash' => '',
		'smartpay' => '',
		'sofort' => PaymentMethod::RTBT,
		'star' => PaymentMethod::CC,
		'stitch' => PaymentMethod::BT,
		'stripedd' => PaymentMethod::STRIPE,
		'thaiqr' => '',
		'touchngo' => '',
		'truemoney' => '',
		'trustly' => PaymentMethod::DD,
		'trustlyeurope' => PaymentMethod::DD,
		'trustlyus' => PaymentMethod::DD,
		'uatp' => PaymentMethod::CC,
		'unionpay' => PaymentMethod::CC,
		'venmo' => PaymentMethod::VENMO,
		'vipps' => '',
		'visa' => PaymentMethod::CC,
		'waave' => '',
		'wechat' => '',
		'webpay' => PaymentMethod::BT,
		'zippay' => '',
	];

	protected static $cardPaymentSubmethods = [
		'amex' => 'amex',
		'bancontact' => '',
		'carte-bancaire' => 'cb',
		'cirrus' => '',
		'culiance' => '',
		'dankort' => '',
		'diners-club' => 'diners',
		'discover' => 'discover',
		'eftpos-australia' => '',
		'elo' => 'elo',
		'hipercard' => 'hipercard',
		'jcb' => 'jcb',
		'maestro' => 'maestro',
		'mastercard' => 'mc',
		'mir' => '',
		'nyce' => '',
		'other' => '',
		'pulse' => '',
		'rupay' => 'rupay',
		'star' => '',
		'uatp' => '',
		'unionpay' => '',
		'visa' => 'visa',
	];

	protected static $ewSubmethods = [
		'alipay' => 'ew_alipay',
		'alipayhk' => 'ew_alipay',
	];

	protected static $rtbtSubmethods = [
		'eps' => 'rtbt',
		'ideal' => 'rtbt',
		'multibanco' => 'rtbt',
		'sepa' => 'rtbt',
		'sofort' => 'rtbt',
	];

	protected static $ddSubmethods = [
		'trustly' => 'ach',
		'trustlyeurope' => '',
		'trustlyus' => 'ach',
	];

	protected static $btSubmethods = [
		'bcp' => 'bcp',
		'pse' => 'pse',
		'netbanking' => 'netbanking',
		'webpay' => 'webpay',
		'stitch' => 'stitch',
		'bancomer' => 'bancomer'
	];

	protected static $cashSubmethods = [
		'pix' => PaymentSubmethod::PIX->value,
		'oxxo' => PaymentSubmethod::CASH_OXXO->value,
		'redpagos' => PaymentSubmethod::CASH_RED_PAGOS->value,
		'boleto' => PaymentSubmethod::CASH_BOLETO->value,
		'abitab' => PaymentSubmethod::CASH_ABITAB->value,
		'rapipago' => PaymentSubmethod::CASH_RAPIPAGO->value,
		'pagoefectivo' => PaymentSubmethod::CASH_PAGO_EFECTIVO->value
	];

	public static function decodePaymentMethod( string $method, ?string $scheme = '' ): array {
		$gravyMethods = self::$paymentMethodMapper;
		$payment_method = $gravyMethods[$method] ?? '';
		$payment_submethod = '';

		switch ( $payment_method ) {
			case PaymentMethod::EW:
				$payment_submethod = $scheme ? self::$ewSubmethods[$scheme] : '';
				break;
			case PaymentMethod::RTBT:
				$payment_submethod = $scheme ? self::$rtbtSubmethods[$scheme] : '';
				break;
			case PaymentMethod::CC:
			case PaymentMethod::APPLE:
			case PaymentMethod::GOOGLE:
				$payment_submethod = $scheme ? self::$cardPaymentSubmethods[$scheme] : '';
				break;
			case PaymentMethod::DD:
				$payment_submethod = self::$ddSubmethods[$method];
				break;
			case PaymentMethod::BT:
				$payment_submethod = self::$btSubmethods[$method];
				break;
			case PaymentMethod::CASH:
				$payment_submethod = self::$cashSubmethods[$method];
				break;
			default:
				break;
		}

		return [ $payment_method, $payment_submethod ];
	}

	public static function getShorthandPaymentMethod( string $paymentMethod ): string {
		$gravyMethods = self::$paymentMethodMapper;
		if ( !isset( $gravyMethods[$paymentMethod] ) ) {
			throw new \InvalidArgumentException( "Payment method '$paymentMethod' not found" );
		}

		return $gravyMethods[$paymentMethod];
	}
}
