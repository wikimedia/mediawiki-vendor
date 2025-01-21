<?php

namespace SmashPig\PaymentProviders\Gravy\Validators;

use SmashPig\PaymentProviders\ValidationException;

class RedirectPaymentProviderValidator extends PaymentProviderValidator {
	/**
	 * @throws ValidationException
	 */
	public function validateOneTimeCreatePaymentInput( array $params ): void {
		$required = [
			'amount',
			'currency',
			'country',
			'order_id',
			'email',
			'first_name',
			'last_name',
		];

		$this->validateFields( $required, $params );
	}
}
