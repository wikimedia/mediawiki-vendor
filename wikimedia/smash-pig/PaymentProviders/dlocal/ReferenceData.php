<?php namespace SmashPig\PaymentProviders\dlocal;

use OutOfBoundsException;
use SmashPig\PaymentData\ReferenceData\NationalCurrencies;

/**
 * These codes are listed per country here
 * https://docs.dlocal.com/docs/payment-method
 */
class ReferenceData {

	protected static $methods = [
		'BANK_TRANSFER' => 'bt',
		'CARD' => 'cc',
		'TICKET' => 'cash',
		'WALLET' => 'bt',
		// api version 1 (AstroPay) style for the audit
		'Bank Transfer' => 'bt',
		'Cash Payment' => 'cash',
		'Credit Card' => 'cc',
		'Debit Card' => 'cc',
	];

	// At least one dLocal bank code is used for both credit cards
	// and bank transfers. We have a different internal code for each.
	protected static $multiTypeSubmethods = [
		'WP' => [
			'cc' => 'webpay',
			'bt' => 'webpay_bt',
		],
	];

	protected static $simpleSubmethods = [
		'AA' => 'alia',
		'AG' => 'argen',
		'AE' => 'amex',
		'AI' => 'cash_abitab',
		'AU' => 'aura',
		'B' => 'bradesco',
		'BB' => 'banco_do_brasil',
		'BC' => 'bcp',
		'BG' => 'cash_provencia_pagos',
		'BL' => 'cash_boleto',
		'BM' => 'cash_banamex',
		'BP' => 'bbva', // Peru, bank transfer
		'BV' => 'cash_bancomer', // Mexico, aka BBVA
		'BX' => 'banco_de_chile',
		'CA' => 'caixa',
		'CB' => 'baloto',
		'CL' => 'cabal',
		'CM' => 'cmr',
		'CR' => 'carulla',
		'CS' => 'cencosud',
		'CZ' => 'codenza',
		'DA' => 'cash_davivienda',
		'DC' => 'diners',
		'DD' => 'dineromail', // (Transfer)
		'DI' => 'discover',
		'DM' => 'cash_dineromail', // (Cash)
		'DS' => 'discover',
		'EF' => 'cash_pago_efectivo',
		'EL' => 'elo',
		'EQ' => 'quindio',
		'EX' => 'almancenes',
		'EY' => 'cash_efecty',
		'H' => 'hsbc',
		'HI' => 'hiper',
		'I' => 'itau',
		'IB' => 'interbank',
		'IO' => 'ach', // South Africa, ACH bt
		'IR' => 'upi', // India. We also get this back for recurring 'paytmwallet' but 'upi' is more common
		'JC' => 'jcb',
		'LD' => 'cabal-debit',
		'LI' => 'lider',
		'MC' => 'mc',
		'MD' => 'mc-debit',
		'MG' => 'magna',
		'ML' => 'mercadolivre',
		'MP' => 'mercadopago',
		'MS' => 'maestro',
		'NB' => 'netbanking', // India
		'NJ' => 'naranja',
		'NT' => 'nativa',
		'OA' => 'oca',
		'OC' => 'banco_de_occidente',
		'OX' => 'cash_oxxo',
		'PA' => 'bcp', // Peru, "via LatinAmericanPayments"
		'PC' => 'pse', // Colombia, "all banks"
		'PF' => 'cash_pago_facil',
		'PQ' => 'pix', // Brazil
		'PR' => 'presto',
		'PW' => 'paytmwallet', // India
		'PY' => 'picpay', // Brazil
		'RE' => 'cash_red_pagos',
		'RL' => 'red_link',
		'RP' => 'cash_rapipago',
		'RU' => 'rupay', // India
		'SB' => 'santander', // Brazil
		'SI' => 'santander_rio', // Argentina
		'SM' => 'cash_santander', // Mexico
		'SP' => 'servipag',
		'SX' => 'surtimax',
		'TS' => 'shopping',
		'UD' => 'upi', // India
		'UI' => 'upi', // India
		'VD' => 'visa-debit',
		'VI' => 'visa',
		'WP' => 'webpay',
	];

	public static function getSimpleSubmethods() {
		return self::$simpleSubmethods;
	}

	public static function decodePaymentMethod( $type, $bankCode ) {
		if ( !array_key_exists( $type, self::$methods ) ) {
			throw new OutOfBoundsException( "Unknown payment method type: {$type}" );
		}

		$method = self::$methods[$type];
		$submethod = self::decodePaymentSubmethod( $method, $bankCode );

		return [ $method, $submethod ];
	}

	public static function decodePaymentSubmethod( $method, $bankCode ) {
		if (
			array_key_exists( $bankCode, self::$multiTypeSubmethods ) &&
			array_key_exists( $method, self::$multiTypeSubmethods[$bankCode] )
		) {
			return self::$multiTypeSubmethods[$bankCode][$method];
		}

		if ( array_key_exists( $bankCode, self::$simpleSubmethods ) ) {
			return self::$simpleSubmethods[$bankCode];
		}

		throw new OutOfBoundsException( "Unknown bank code: {$bankCode}" );
	}

	/**
	 * Looks up the dLocal payment_method_id corresponding to our payment_submethod.
	 * In some cases a single submethod can map to different payment_method_ids
	 * depending on other parameters, so we accept the full params array.
	 * @param array $params should at least have payment_method_id set.
	 * @return string|null
	 */
	public static function getPaymentMethodId( array $params ): ?string {
		// First handle special cases that depend on more than just the submethod
		if ( BankTransferPaymentProvider::isIndiaRecurring( $params ) ) {
			// Recurring UPI and PayTM payments need to be charged as IR, 'India Recurring'
			return 'IR';
		}
		if ( !empty( $params['upi_id'] ) ) {
			// This is specifically the code for the direct version of one-time UPI payments
			return 'UD';
		}
		if ( $params['payment_submethod'] === 'upi' ) {
			// Need to skip the lookup below for non-recurring UPI since three codes map
			// to it in the lookup table. It maps to UI or UD depending on the flow, but
			// we will handle the UD case elsewhere. Here we just default to UI.
			return 'UI';
		}
		if ( $params['payment_submethod'] === 'webpay_bt' ) {
			return 'WP';
		}
		foreach ( self::$simpleSubmethods as $paymentMethodId => $submethod ) {
			if ( $submethod === $params['payment_submethod'] ) {
				return $paymentMethodId;
			}
		}
		// Don't throw an error if we don't have a mapping here. In some cases we
		// can use a catch-all payment_method_id that covers a whole swath of
		// submethods, such as 'CARD'
		return null;
	}

	/**
	 * Since we do not have country and fiscal number saved for contribution
	 * check \CRM_Core_Payment_SmashPigRecurringProcessor::getPaymentParams
	 * @param string $currency
	 * @return string|null
	 */
	public static function getPairedCountryFromCurrency( string $currency ): ?string {
		foreach ( NationalCurrencies::getNationalCurrencies() as $country => $defaultCurrency ) {
			if ( $defaultCurrency === $currency ) {
				return $country;
			}
		}
		return null;
	}

}
