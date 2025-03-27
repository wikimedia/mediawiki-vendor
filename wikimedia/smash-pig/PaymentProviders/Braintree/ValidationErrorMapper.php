<?php

namespace SmashPig\PaymentProviders\Braintree;

/**
 * Maps Braintree validation error codes to normalized field names
 */
class ValidationErrorMapper {
	/**
	 * @var array
	 * Braintree transaction validation error matching normalized field names.
	 * normalized fields: https://phabricator.wikimedia.org/diffusion/EDOI/browse/master/gateway_common/DonationData.php$950
	 */
	protected static $validationErrorInputPathMap = [
		'amount' => 'amount',
		'orderId' => 'order_id',
		'paymentMethodId' => 'payment_method'
	];

	/**
	 * @param array|null $inputPath
	 * @return string
	 */
	public static function getValidationErrorField( ?array $inputPath = null ): ?string {
		if ( $inputPath ) {
			$errorProperty = $inputPath[count( $inputPath ) - 1];
			if ( array_key_exists( $errorProperty, self::$validationErrorInputPathMap ) ) {
				return self::$validationErrorInputPathMap[$errorProperty];
			}
		}
		return 'general';
	}
}
