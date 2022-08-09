<?php

namespace SmashPig\PaymentProviders\Adyen;

/**
 * Maps Adyen error codes to normalized field names
 */
class ValidationErrorMapper {
	// TODO: after refactoring ValidationError (https://phabricator.wikimedia.org/T294957)
	// these values will need more than just a field name
	protected static $validationErrorFields = [
		'101' => 'card_num', // Invalid card number
		'102' => 'card_num', // Unable to determine variant
		'103' => 'cvv',
		'905' => 'payment_submethod', // Unsupported card type
		'905_1' => 'payment_submethod', // Unsupported card type
		'905_3' => 'payment_submethod', // Unsupported card type
	];

	public static function getValidationErrorField( $errorCode ): ?string {
		return self::$validationErrorFields[$errorCode] ?? null;
	}
}
