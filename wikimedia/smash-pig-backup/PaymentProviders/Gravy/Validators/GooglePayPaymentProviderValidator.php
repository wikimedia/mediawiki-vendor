<?php

namespace SmashPig\PaymentProviders\Gravy\Validators;

use SmashPig\PaymentProviders\ValidationException;

class GooglePayPaymentProviderValidator extends PaymentProviderValidator {

	/**
	 * @throws ValidationException
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
