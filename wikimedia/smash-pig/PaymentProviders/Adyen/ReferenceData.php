<?php namespace SmashPig\PaymentProviders\Adyen;

use OutOfBoundsException;

class ReferenceData {

	/**
	 * Example for adding a new Payment Method
	 * 	'PaymentMethodNameFromPaymentProcessor' => [
	 * 	  'method' => 'OurNameForThePaymentMethod',
	 *    'submethod' => 'OurNameForTheSubmethod',
	 * 	  Variants are optional, sometimes there is a variant that comes in with the payment method
	 *    'variants' => [
	 *      'PaymentMethodVariantNameFromPaymentProcessor' => 'OurNameForThePaymentMethodVariant',
	 * 	  ],
	 *  ],
	 */

	protected static $methods = [
		'alipay' => [
			'method' => 'ew',
			'submethod' => 'ew_alipay',
		],
		'amex' => [
			'method' => 'cc',
			'submethod' => 'amex',
		],
		'amex_applepay' => [
			'method' => 'apple',
			'submethod' => 'amex',
		],
		'amex_googlepay' => [
			'method' => 'google',
			'submethod' => 'amex',
		],
		'applepay' => [
			'method' => 'apple',
			'submethod' => 'apple'
		],
		'bijcard' => [
			'method' => 'cc',
			'submethod' => 'bij',
		],
		// International Bank Transfer (IBAN)
		'banktransfer_IBAN' => [
			'method' => 'bt',
			'submethod' => 'iban',
		],
		'cartebancaire' => [
			'method' => 'cc',
			'submethod' => 'cb',
		],
		'cartebancaire_applepay' => [
			'method' => 'apple',
			'submethod' => 'cb',
		],
		// China Union Pay
		'cup' => [
			'method' => 'cc',
			'submethod' => 'cup',
		],
		'diners' => [
			'method' => 'cc',
			'submethod' => 'diners',
		],
		// Sofortüberweisung
		'directEbanking' => [
			'method' => 'rtbt',
			'submethod' => 'rtbt_sofortuberweisung',
		],
		'discover' => [
			'method' => 'cc',
			'submethod' => 'discover',
		],
		'discover_applepay' => [
			'method' => 'apple',
			'submethod' => 'discover',
		],
		'discover_googlepay' => [
			'method' => 'google',
			'submethod' => 'discover',
		],
		'dotpay' => [
			'method' => 'ew',
			'submethod' => 'ew_dotpay',
		],
		'electron_applepay' => [
			'method' => 'apple',
			'submethod' => 'visa-electron',
		],
		'electron_googlepay' => [
			'method' => 'google',
			'submethod' => 'visa-electron',
		],
		'googlepay' => [
			'method' => 'google',
			'submethod' => 'google'
		],
		'ideal' => [
			'method' => 'rtbt',
			'submethod' => 'rtbt_ideal',
		],
		'interlink' => [
			'method' => 'cc',
			'submethod' => 'visa',
		],
		'jcb' => [
			'method' => 'cc',
			'submethod' => 'jcb',
		],
		'jcb_applepay' => [
			'method' => 'apple',
			'submethod' => 'jcb',
		],
		'jcbprepaidanonymous' => [
			'method' => 'cc',
			'submethod' => 'jcb',
		],
		'mc' => [
			'method' => 'cc',
			'submethod' => 'mc',
			'variants' => [
				'mcdebit' => 'mc-debit',
			],
		],
		'mc_applepay' => [
			'method' => 'apple',
			'submethod' => 'mc',
		],
		'mc_googlepay' => [
			'method' => 'google',
			'submethod' => 'mc',
		],
		'maestro' => [
			'method' => 'cc',
			'submethod' => 'maestro',
		],
		'multibanco' => [
			'method' => 'rtbt',
			'submethod' => 'rtbt_multibanco',
		],
		'safetypay' => [
			'method' => 'rtbt',
			'submethod' => 'rtbt_safetypay',
		],
		// 'sepadirectdebit' => [
		//	'method' => 'dd',
		//	'submethod' => 'dd_sepa',
		// ],
		// Technically, sepadirectdebit is method 'dd', but under Adyen we only ever use it for
		// recurring installments on iDeal payments. We tag it as iDeal here to be consistent
		// with how the donations are sent to the queue from the nightly charge job.
		'sepadirectdebit' => [
			'method' => 'rtbt',
			'submethod' => 'rtbt_ideal',
		],
		'tenpay' => [
			'method' => 'ew',
			'submethod' => 'ew_tenpay',
		],
		'trustly' => [
			'method' => 'obt',
			'submethod' => 'trustly',
		],
		'visa' => [
			'method' => 'cc',
			'submethod' => 'visa',
			'variants' => [
				'visabeneficial' => 'visa-beneficial', // guessing at Adyen code
				'visadebit' => 'visa-debit',
				'visaelectron' => 'visa-electron', // guessing at Adyen code
			]
		],
		'visa_applepay' => [
			'method' => 'apple',
			'submethod' => 'visa',
		],
		'visa_googlepay' => [
			'method' => 'google',
			'submethod' => 'visa',
		],
		// Debit card issued by Visa Europe
		'vpay' => [
			'method' => 'cc',
			'submethod' => 'visa-debit',
		],
		'visadankort' => [
			'method' => 'cc',
			'submethod' => 'visa',
		]
	];

	/**
	 * @param string $method Adyen's 'Payment Method'
	 * @param string $variant Adyen's 'Payment Method Variant'
	 * @return array first entry is our payment_method, second is our payment_submethod
	 */
	public static function decodePaymentMethod( $method, $variant ) {
		if ( !array_key_exists( $method, self::$methods ) ) {
			throw new OutOfBoundsException( "Unknown Payment Method $method " );
		}
		$entry = self::$methods[$method];
		$ourMethod = $entry['method'];
		if ( $variant && array_key_exists( 'variants', $entry ) &&
			array_key_exists( $variant, $entry['variants'] ) ) {
			$ourSubmethod = $entry['variants'][$variant];
		} else {
			$ourSubmethod = $entry['submethod'];
		}
		return [ $ourMethod, $ourSubmethod ];
	}
}
