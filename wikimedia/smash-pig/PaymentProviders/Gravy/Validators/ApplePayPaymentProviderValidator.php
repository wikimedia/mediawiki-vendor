<?php

namespace SmashPig\PaymentProviders\Gravy\Validators;

use SmashPig\PaymentProviders\ValidationException;

/**
 * This class provides input validation for Apple payment requests.
 */
class ApplePayPaymentProviderValidator extends PaymentProviderValidator {

	/**
	 * Checks the Apple session creation input parameters for correctness and completeness.
	 *
	 * @param array $params
	 * @throws ValidationException
	 * @return void
	 */
	public function validateCreateSessionInput( array $params ): void {
		$required = [
			'validation_url',
			'domain_name'
		];

		$this->validateFields( $required, $params );
	}

	/**
	 * Checks the one time Apple create payment input parameters for correctness and completeness.
	 *
	 * @param array $params
	 * @throws ValidationException
	 * @return void
	 */
	public function validateOneTimeCreatePaymentInput( array $params ): void {
		parent::validateOneTimeCreatePaymentInput( $params );

		$required = [
			'payment_token',
			'email',
			'first_name',
			'last_name'
		];

		$this->validateFields( $required, $params );
	}

	/**
	 * Override the parent method because we do not require fiscal number
	 * validation for Apple Pay payments
	 */
	protected function addCountrySpecificRequiredFields( array $params ): array {
		return [];
	}

}
