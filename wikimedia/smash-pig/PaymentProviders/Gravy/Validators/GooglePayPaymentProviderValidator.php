<?php

namespace SmashPig\PaymentProviders\Gravy\Validators;

use SmashPig\PaymentProviders\ValidationException;

/**
 * This class provides input validation for Google payment requests.
 */
class GooglePayPaymentProviderValidator extends PaymentProviderValidator {

	/**
	 * Checks the one time Google create payment input parameters for correctness and completeness.
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
			'full_name',
			'card_suffix',
			'card_scheme'
		];

		$this->validateFields( $required, $params );
	}

	/**
	 * Override the parent method because we do not require fiscal number
	 * validation for Google Pay payments
	 */
	protected function addCountrySpecificRequiredFields( array $params ): array {
		return [];
	}

}
