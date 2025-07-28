<?php

namespace SmashPig\PaymentProviders\Gravy\Validators;

use SmashPig\PaymentProviders\ValidationException;

/**
 * This class provides input validation for PayPal redirect payment requests.
 */
class PaypalPaymentProviderValidator extends PaymentProviderValidator {
	/**
	 * Checks the one time PayPal create payment input parameters for correctness and completeness.
	 *
	 * @param array $params
	 * @throws ValidationException
	 * @return void
	 */
	public function validateOneTimeCreatePaymentInput( array $params ): void {
		parent::validateOneTimeCreatePaymentInput( $params );

		$required = $this->addCountrySpecificRequiredFields( $params );
		$this->validateFields( $required, $params );
	}

	/**
	 * Summary of addCountrySpecificRequiredFields
	 * @param array $params
	 * @return array
	 */
	protected function addCountrySpecificRequiredFields( array $params ): array {
		// PayPal does not require tax id to process payments
		return [];
	}
}
