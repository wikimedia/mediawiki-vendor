<?php

namespace SmashPig\PaymentProviders\Ingenico;

use OutOfBoundsException;

class ReferenceData {
	// FIXME: replace this whole class with payment_(sub)method.yaml files
	// See https://epayments-api.developer-ingenico.com/s2sapi/v1/en_US/java/paymentproducts.html
	// Also see wmf_civicrm.module, lines 618-699

	protected static $methods = [
		'1' => [ 'payment_method' => 'cc', 'payment_submethod' => 'visa' ],
		'2' => [ 'payment_method' => 'cc', 'payment_submethod' => 'amex' ],
		'3' => [ 'payment_method' => 'cc', 'payment_submethod' => 'mc' ],
		'11' => [ 'payment_method' => 'bt', 'payment_submethod' => 'bt' ],
		'114' => [ 'payment_method' => 'cc', 'payment_submethod' => 'visa-debit' ],
		'117' => [ 'payment_method' => 'cc', 'payment_submethod' => 'maestro' ],
		'118' => [ 'payment_method' => 'cc', 'payment_submethod' => 'solo' ],
		'119' => [ 'payment_method' => 'cc', 'payment_submethod' => 'mc-debit' ],
		'122' => [ 'payment_method' => 'cc', 'payment_submethod' => 'visa-electron' ],
		'124' => [ 'payment_method' => 'cc', 'payment_submethod' => 'laser' ],
		'125' => [ 'payment_method' => 'cc', 'payment_submethod' => 'jcb' ],
		'128' => [ 'payment_method' => 'cc', 'payment_submethod' => 'discover' ],
		'130' => [ 'payment_method' => 'cc', 'payment_submethod' => 'cb' ],
		'132' => [ 'payment_method' => 'cc', 'payment_submethod' => 'diners' ],
		'135' => [ 'payment_method' => 'cc', 'payment_submethod' => 'cabal' ],
		'136' => [ 'payment_method' => 'cc', 'payment_submethod' => 'naranja' ],
		'140' => [ 'payment_method' => 'cc', 'payment_submethod' => 'argen' ],
		'145' => [ 'payment_method' => 'cc', 'payment_submethod' => 'nativa' ],
		'147' => [ 'payment_method' => 'cc', 'payment_submethod' => 'elo' ],
		'148' => [ 'payment_method' => 'cc', 'payment_submethod' => 'hiper' ],
		'149' => [ 'payment_method' => 'cc', 'payment_submethod' => 'shopping' ],
		'500' => [ 'payment_method' => 'obt', 'payment_submethod' => 'bpay' ],
		'701' => [ 'payment_method' => 'dd', 'payment_submethod' => 'dd_nl' ],
		'702' => [ 'payment_method' => 'dd', 'payment_submethod' => 'dd_de' ],
		'703' => [ 'payment_method' => 'dd', 'payment_submethod' => 'dd_at' ],
		'704' => [ 'payment_method' => 'dd', 'payment_submethod' => 'dd_fr' ],
		'705' => [ 'payment_method' => 'dd', 'payment_submethod' => 'dd_gb' ],
		'706' => [ 'payment_method' => 'dd', 'payment_submethod' => 'dd_be' ],
		'707' => [ 'payment_method' => 'dd', 'payment_submethod' => 'dd_ch' ],
		'708' => [ 'payment_method' => 'dd', 'payment_submethod' => 'dd_it' ],
		'709' => [ 'payment_method' => 'dd', 'payment_submethod' => 'dd_es' ],
		'805' => [ 'payment_method' => 'rtbt', 'payment_submethod' => 'rtbt_nordea_sweden' ],
		'809' => [ 'payment_method' => 'rtbt', 'payment_submethod' => 'rtbt_ideal' ],
		'810' => [ 'payment_method' => 'rtbt', 'payment_submethod' => 'rtbt_enets' ],
		'836' => [ 'payment_method' => 'rtbt', 'payment_submethod' => 'rtbt_sofortuberweisung' ],
		'840' => [ 'payment_method' => 'ew', 'payment_submethod' => 'ew_paypal' ],
		'841' => [ 'payment_method' => 'ew', 'payment_submethod' => 'ew_webmoney' ],
		'843' => [ 'payment_method' => 'ew', 'payment_submethod' => 'ew_moneybookers' ],
		'845' => [ 'payment_method' => 'ew', 'payment_submethod' => 'ew_cashu' ],
		'849' => [ 'payment_method' => 'ew', 'payment_submethod' => 'ew_yandex' ],
		'856' => [ 'payment_method' => 'rtbt', 'payment_submethod' => 'rtbt_eps' ],
		'861' => [ 'payment_method' => 'ew', 'payment_submethod' => 'ew_alipay' ],
		// Ingenico docs say 1070 is SEPA refund. The payments we've seen this code
		// for so far have been iDEAL bank transfers.
		'1070' => [ 'payment_method' => 'rtbt', 'payment_submethod' => 'rtbt_ideal' ],
		'1503' => [ 'payment_method' => 'cash', 'payment_submethod' => 'cash_boleto' ],
	];

	/**
	 * Gets our normalized payment_method and payment_submethod params from the
	 * codes that GC uses
	 *
	 * @param string $paymentProductId
	 * @return array containing payment_method and payment_submethod
	 */
	public static function decodePaymentMethod( string $paymentProductId ): array {
		if ( !array_key_exists( $paymentProductId, self::$methods ) ) {
			throw new OutOfBoundsException( "Unknown Payment Product ID $paymentProductId " );
		}
		return self::$methods[$paymentProductId];
	}
}
