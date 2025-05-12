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
		$defaultRequiredFields = [
			'amount',
			'currency',
			'country',
			'order_id'
		];

		$required = array_merge(
			$defaultRequiredFields,
			$this->addCountrySpecificRequiredFields( $params )
		);

		$this->validateFields( $required, $params );
	}
}
