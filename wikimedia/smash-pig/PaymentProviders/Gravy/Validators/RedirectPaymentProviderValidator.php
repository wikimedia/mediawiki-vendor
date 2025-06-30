<?php

namespace SmashPig\PaymentProviders\Gravy\Validators;

use SmashPig\PaymentProviders\ValidationException;

/**
 * This class provides input validation for redirect payment requests.
 *
 * If a specific redirect payment method requires custom validation logic for any input,
 * a new validator class should be created that extends this base class. The subclass should override
 * or extend the method to implement the required custom validation.
 */
class RedirectPaymentProviderValidator extends PaymentProviderValidator {
	/**
	 * Checks the one time create payment input parameters for correctness and completeness.
	 * Extend or override this method, if a specific redirect payment method's create payment
	 * requires custom validation logic.
	 *
	 * @param array $params
	 * @throws ValidationException
	 * @return void
	 */
	public function validateOneTimeCreatePaymentInput( array $params ): void {
		parent::validateOneTimeCreatePaymentInput( $params );

		$defaultRequiredFields = [
			'email',
			'first_name',
			'last_name',
		];

		$required = array_merge(
			$defaultRequiredFields,
			$this->addCountrySpecificRequiredFields( $params )
		);

		$this->validateFields( $required, $params );
	}
}
