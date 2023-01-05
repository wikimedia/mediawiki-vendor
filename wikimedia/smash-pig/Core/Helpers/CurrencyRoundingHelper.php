<?php

namespace SmashPig\Core\Helpers;

class CurrencyRoundingHelper {

	/**
	 * These currencies cannot have cents.
	 *
	 * @var string[]
	 */
	public static $noDecimalCurrencies = [
		'CLP',
		'DJF',
		'IDR',
		'JPY',
		'KMF',
		'KRW',
		'MGA',
		'PYG',
		'VND',
		'XAF',
		'XOF',
		'XPF',
	];

	/**
	 * Currencies whose minor unit is thousandths (three decimal places)
	 *
	 * @var string[]
	 */
	public static $threeDecimalCurrencies = [
		'BHD',
		'CLF',
		'IQD',
		'KWD',
		'LYD',
		'MGA',
		'MRO',
		'OMR',
		'TND',
	];

	/**
	 * Some currencies, like JPY, don't exist in fractional amounts.
	 * This rounds an amount to the appropriate number of decimal places.
	 *
	 * @param float $amount
	 * @param string $currencyCode
	 *
	 * @return string rounded amount
	 */
	public static function round( float $amount, string $currencyCode ): string {
		if ( self::isFractionalCurrency( $currencyCode ) ) {
			$precision = 2;
			if ( self::isThreeDecimalCurrency( $currencyCode ) ) {
				$precision = 3;
			}
			return number_format( $amount, $precision, '.', '' );
		} else {
			return (string)floor( $amount );
		}
	}

	/**
	 * @param string $currencyCode The three-character currency code.
	 *
	 * @return bool
	 */
	public static function isFractionalCurrency( string $currencyCode ): bool {
		if ( in_array( strtoupper( $currencyCode ),
			static::$noDecimalCurrencies ) ) {
			return false;
		}
		return true;
	}

	/**
	 * Checks if ISO 4217 (https://www.iso.org/iso-4217-currency-codes.html)
	 * defines the currency's minor units as being expressed in thousandths.
	 *
	 * @param string $currencyCode The three-character currency code.
	 *
	 * @return bool
	 */
	public static function isThreeDecimalCurrency( string $currencyCode ): bool {
		if ( in_array( strtoupper( $currencyCode ),
			static::$threeDecimalCurrencies ) ) {
			return true;
		}
		return false;
	}

}
