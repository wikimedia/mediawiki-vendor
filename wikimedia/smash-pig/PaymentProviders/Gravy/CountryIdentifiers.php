<?php

namespace SmashPig\PaymentProviders\Gravy;

/**
 * Get the Primary Identifier (Tax IDs, Business IDs, Personal IDs) for a given country.
 * This is currently specific to dLocal-supported countries but could be expanded if needed in the future
 *
 * How it works:
 * 1. Determine if the provided country code is supported (exists in IDENTIFIERS).
 * 2. If the country has a single known identifier, return it immediately.
 * 3. If the country has multiple possible identifiers, look up the patterns in $countryIdentifierRules.
 * 4. Test the user-supplied $fiscalNumber against each regex.
 *    - If one matches and its 'type' is present in the list of possible identifiers, return that 'type'.
 * 5. If none of the patterns match, default to the first identifier in IDENTIFIERS.
 *
 * Example usage:
 * CountryIdentifiers::getGravyCodeForSuppliedCountryIdentifier('AR', '1234567'); // return: ar.dni
 */
class CountryIdentifiers {

	/**
	 * Country-specific identifiers
	 * Taken from https://docs.gr4vy.com/reference/transactions/new-transaction#response-buyer-billing-details-tax-id-kind
	 */
	public const IDENTIFIERS = [
		// Argentina (AR)
		'AR' => [
			'ar.cuit', // Unique Taxpayer Identification Code (CUIT)
			'ar.dni', // Documento Nacional de Identidad (DNI)
			'ar.cuil', // Unique Labor Identification Code (CUIL)
		],
		// Brazil (BR)
		'BR' => [
			'br.cnpj', // Business Tax ID
			'br.cpf', // Personal Taxpayer ID
		],
		// Chile (CL)
		'CL' => [ 'cl.tin' ], // Taxpayer Identification Number (TIN)
		// Colombia (CO) -- UPDATED to support co.cc, co.nit, and co.itin
		'CO' => [
			'co.nit', // Número de Identificación Tributaria (business)
			'co.itin', // Individual Taxpayer Identification Number
			// 'co.cc',   // Cédula de Ciudadanía (commented out for now)
		],
		// India (IN)
		'IN' => [ 'in.gst' ], // Goods and Services Tax
		// Mexico (MX)
		'MX' => [ 'mx.curp' ], // Personal Identity Code
		// South Africa (ZA)
		'ZA' => [ 'za.vat' ], // South African VAT Number
		// Uruguay (UY)
		'UY' => [ 'uy.rut' ], // Registro Único Tributario (RUT)
		// Peru (PE)
		'PE' => [ 'pe.ruc' ], // Registro Único de Contribuyentes (RUC)
	];

	/**
	 * Regex patterns for country-specific identifiers
	 *
	 * See: https://django-localflavor.readthedocs.io/en/latest/
	 * and extensions/DonationInterface/gateway_common/FiscalNumber.php
	 *
	 * @var array|array[]
	 */
	protected static array $countryIdentifierRules = [
		'AR' => [
			'patterns' => [
				[
					'regex' => '/^\d{7,10}$/', // 7–10 digits => DNI
					'type' => 'ar.dni',
				],
				[
					'regex' => '/^\d{11}$/', // 11 digits => CUIT
					'type' => 'ar.cuit',
				],
				[
					'regex' => '/^\d{11}$/', // 11 digits => CUIL (same length as CUIT)
					'type' => 'ar.cuil',
				],
			],
		],
		'BR' => [
			'patterns' => [
				[
					'regex' => '/^\d{14}$/', // 14 digits => CNPJ
					'type' => 'br.cnpj',
				],
				[
					'regex' => '/^\d{11}$/', // 11 digits => CPF
					'type' => 'br.cpf',
				],
			],
		],
		'CL' => [
			'patterns' => [
				[
					'regex' => '/^\d{8,9}$/', // Basic numeric length check
					'type' => 'cl.tin',
				],
			],
		],
		'CO' => [
			'patterns' => [
				[
					// 9–10 digits
					'regex' => '/^\d{9,10}$/',
					'type' => 'co.nit',
				],
				[
					// 9 digits
					'regex' => '/^\d{9}$/',
					'type' => 'co.itin',
				],
				[
					// 6–10 digits => typical "Cédula de Ciudadanía"
					'regex' => '/^\d{6,10}$/',
					'type' => 'co.cc',
				],
			],
		],
		'IN' => [
			'patterns' => [
				[
					// Basic GST format example
					'regex' => '/^[A-Za-z]{3}[abcfghljptfABCFGHLJPTF]{1}[A-Za-z]{1}\d{4}[A-Za-z]{1}$/',
					'type' => 'in.gst',
				],
			],
		],
		'MX' => [
			'patterns' => [
				[
					// Typically: 4 letters + 6 digits + 6 alphanumeric + 2 digits
					'regex' => '/^[A-Z][AEIOU][A-Z]{2}\d{6}[HM][A-Z]{5}\d{2}$/i',
					'type' => 'mx.curp',
				],
			],
		],
		'PE' => [
			'patterns' => [
				[
					// Standard Peruvian RUC is 11 digits
					'regex' => '/^\d{11}$/',
					'type' => 'pe.ruc',
				],
			],
		],
		'UY' => [
			'patterns' => [
				[
					// typical RUT can be 12 digits, but it may vary.
					'regex' => '/^\d{12}$/',
					'type' => 'uy.rut',
				],
				[
					// typical CI can be 6-8 digits, according to dlocal docs.
					'regex' => '/^\d{6,8}$/',
					'type' => 'uy.ci',
				],
			],
		],
		'ZA' => [
			'patterns' => [
				[
					'regex' => '/^[0-9]{2}[01][0-9][0-3][0-9]{5}[01][89][0-9]$/',
					'type' => 'za.vat',
				],
			],
		],
	];

	public static function countryUsesFiscalNumberForPayments( string $countryCode ): bool {
		return array_key_exists( strtoupper( $countryCode ), self::IDENTIFIERS );
	}

	/**
	 * Get the Gravy identifier code for a given country, based on a provided $fiscalNumber.
	 * If there's more than one valid ID for that country, we'll attempt regex matching
	 * in $countryIdentifierRules. If no pattern matches, we default to the first ID.
	 *
	 * @param string $countryCode Two-letter country code
	 * @param string $fiscalNumber The user-supplied ID/tax number to test
	 *
	 * @return string|null Returns the matching Gravy identifier code (e.g. "ar.dni") or null if the country is unsupported.
	 */
	public static function getGravyTaxIdTypeForSuppliedCountryIdentifier(
		string $countryCode,
		string $fiscalNumber
	): ?string {
		$countryCode = strtoupper( $countryCode );

		// Look up possible code(s) in IDENTIFIERS
		$gravyCodes = self::IDENTIFIERS[$countryCode] ?? null;

		// If not defined, we can't handle this country
		if ( $gravyCodes === null ) {
			return null;
		}

		// If there's only a single result, return it
		if ( count( $gravyCodes ) === 1 ) {
			return $gravyCodes[0];
		}

		// We have multiple code options -> check for matching pattern
		$rules = self::$countryIdentifierRules[$countryCode] ?? null;
		if ( $rules && !empty( $rules['patterns'] ) ) {
			foreach ( $rules['patterns'] as $patternRule ) {
				if ( preg_match( $patternRule['regex'], $fiscalNumber ) ) {
					$matchedType = $patternRule['type'];
					// Return this code if it exists among the array of possible codes
					if ( in_array( $matchedType, $gravyCodes, true ) ) {
						return $matchedType;
					}
				}
			}
		}

		// If no pattern matched, default to the first code in the array
		return $gravyCodes[0];
	}
}
