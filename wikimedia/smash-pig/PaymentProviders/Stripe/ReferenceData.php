<?php namespace SmashPig\PaymentProviders\Stripe;

class ReferenceData {

	/**
	 * @var array mapping from Stripe's payment_method_type to our
	 * method/submethod.
	 *
	 * This is intentionally partial for now: types not listed here pass
	 * through unchanged (see decodePaymentMethod()) rather than throwing,
	 * since audit make-missing messages should be rejected individually
	 * by the queue consumer instead of aborting the whole file's parse.
	 *
	 * Example for adding a new Payment Method
	 *  'PaymentMethodTypeFromStripe' => [
	 *    'method' => 'OurNameForThePaymentMethod',
	 *    'submethod' => 'OurNameForTheSubmethod',
	 *  ],
	 */
	protected static array $methods = [
		'card' => [
			'method' => 'cc',
			'submethod' => '',
		],
		// Give Lively's "Giving Basket" feature sends a Stripe Connect
		// transfer (stripe_account) rather than a card charge.
		'stripe_account' => [
			'method' => 'cc',
			'submethod' => '',
		],
	];

	/**
	 * @param string $method Stripe's payment_method_type
	 * @return array first entry is our payment_method, second is our payment_submethod
	 */
	public static function decodePaymentMethod( string $method ): array {
		if ( !array_key_exists( $method, self::$methods ) ) {
			return [ $method, '' ];
		}
		$entry = self::$methods[$method];
		return [ $entry['method'], $entry['submethod'] ];
	}
}
