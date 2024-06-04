<?php namespace SmashPig\PaymentProviders\Adyen;

use OutOfBoundsException;

class ReferenceData {

	/**
	 * Example for adding a new Payment Method
	 *  'PaymentMethodNameFromPaymentProcessor' => [
	 *    'method' => 'OurNameForThePaymentMethod',
	 *    'submethod' => 'OurNameForTheSubmethod',
	 *    Variants are optional, sometimes there is a variant that comes in with the payment method
	 *    'variants' => [
	 *      'PaymentMethodVariantNameFromPaymentProcessor' => [
	 *        'method' =>	 'OurNameForTheVariantMethod'
	 *        'submethod' => 'OurNameForTheVariantSubmethod',
	 *      ],
	 *    ],
	 *  ],
	 */

	protected static $methods = [
		'ach' => [
			'method' => 'dd',
			'submethod' => 'ach',
		],
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
		'eftpos_australia' => [
			'method' => 'cc',
			'submethod' => 'mc',
			'variants' => [
				'mc_googlepay' => [
					'method' => 'google',
					'submethod' => 'mc',
				],
			]
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
		'googlewallet' => [
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
			'variants' => [
				'visa_applepay' => [
					'method' => 'apple',
					'submethod' => 'visa',
				],
			]
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
				'mcdebit' => [
					'method' => 'cc',
					'submethod' => 'mc-debit',
				],
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
		'maestro_googlepay' => [
			'method' => 'google',
			'submethod' => 'maestro',
		],
		'multibanco' => [
			'method' => 'rtbt',
			'submethod' => 'rtbt_multibanco',
		],
		'nyce' => [
			'method' => 'cc',
			'submethod' => 'mc',
			'variants' => [
				'mc_googlepay' => [
					'method' => 'google',
					'submethod' => 'mc',
				],
			],
		],
		'onlineBanking_CZ' => [
			'method' => 'bt',
			'submethod' => '',
		],
		'pulse' => [
			'method' => 'cc',
			'submethod' => 'visa',
			'variants' => [
				'visa_applepay' => [
					'method' => 'apple',
					'submethod' => 'visa',
				],
			]
		],
		'safetypay' => [
			'method' => 'rtbt',
			'submethod' => 'rtbt_safetypay',
		],
		// 'sepadirectdebit' => [
		//	'method' => 'dd',
		//	'submethod' => 'dd_sepa',
		// ],
		// Technically, sepadirectdebit is method 'dd', but under Adyen we also use it for
		// recurring installments on iDeal payments, and other sepadirectdebit so make it consistant use rtbt.
		'sepadirectdebit' => [
			'method' => 'rtbt',
			'submethod' => 'sepadirectdebit',
		],
		'star' => [
			'method' => 'cc',
			'submethod' => 'visa',
			'variants' => [
				'visa_applepay' => [
					'method' => 'apple',
					'submethod' => 'visa',
				],
			]
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
				'visabeneficial' => [
					'method' => 'cc',
					'submethod' => 'visa-beneficial', // guessing at Adyen code
				],
				'visadebit' => [
					'method' => 'cc',
					'submethod' => 'visa-debit',
				],
				'visaelectron' => [
					'method' => 'cc',
					'submethod' => 'visa-electron', // guessing at Adyen code
				],
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
			throw new OutOfBoundsException( "Unknown Payment Method '$method'" );
		}
		$entry = self::$methods[$method];
		$ourMethod = $entry['method'];
		if ( $variant && array_key_exists( 'variants', $entry ) &&
			array_key_exists( $variant, $entry['variants'] ) ) {
			$ourMethod = $entry['variants'][$variant]['method'];
			$ourSubmethod = $entry['variants'][$variant]['submethod'];
		} else {
			$ourSubmethod = $entry['submethod'];
		}
		return [ $ourMethod, $ourSubmethod ];
	}
}
