<?php namespace SmashPig\PaymentProviders\AstroPay;

use OutOfBoundsException;

/**
 * These codes are listed per country at e.g.
 * https://docs.dlocal.com/api-documentation/payins-api-reference/payment-methods/brazil
 */
class ReferenceData {

	protected static $methods = [
		'Bank Transfer' => 'bt',
		'Cash Payment' => 'cash',
		'Credit Card' => 'cc',
		'Debit Card' => 'cc',
	];

	// At least one AstroPay bank code is used for both credit cards
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
		'UI' => 'upi', // India
		'VD' => 'visa-debit',
		'VI' => 'visa',
		'WP' => 'webpay',
	];

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
}
