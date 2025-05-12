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
		$required = [
			'payment_token',
			'amount',
			'currency',
			'country',
			'order_id',
			'email',
			'full_name',
			'card_suffix',
			'card_scheme'
		];

		$this->validateFields( $required, $params );
	}

}
