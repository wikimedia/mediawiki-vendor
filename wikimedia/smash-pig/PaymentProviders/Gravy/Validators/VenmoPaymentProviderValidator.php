<?php

namespace SmashPig\PaymentProviders\Gravy\Validators;

/**
 * This class provides input validation for Venmo redirect payment requests.
 */
class VenmoPaymentProviderValidator extends PaymentProviderValidator {

	/**
	 * Summary of addCountrySpecificRequiredFields
	 * @param array $params
	 * @return array
	 */
	protected function addCountrySpecificRequiredFields( array $params ): array {
		// Venmo does not require tax id to process payments
		return [];
	}
}
