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
		'JOD',
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
		if ( static::isFractionalCurrency( $currencyCode ) ) {
			$precision = 2;
			if ( static::isThreeDecimalCurrency( $currencyCode ) ) {
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

	/**
	 * Some processors require amounts to be passed as an integer representing
	 * the value in minor units for that currency. Currencies that lack a minor
	 * unit (such as JPY) are simply passed as is. For example: USD 10.50 would
	 * be changed to 1050, JPY 150 would be passed as 150.
	 *
	 * @param float $amount The amount in major units
	 * @param string $currencyCode ISO currency code
	 * @return int The amount in minor units
	 */
	public static function getAmountInMinorUnits( float $amount, string $currencyCode ): int {
		if ( static::isThreeDecimalCurrency( $currencyCode ) ) {
			$amount = $amount * 1000;
		} elseif ( static::isFractionalCurrency( $currencyCode ) ) {
			$amount = $amount * 100;
		}
		// PHP does indeed need us to round it off before casting to int.
		// For example, try $36.80
		return (int)round( $amount );
	}
}
