<?php

namespace SmashPig\PaymentProviders\Gravy\Validators;

use SmashPig\PaymentProviders\ValidationException;

class ApplePayPaymentProviderValidator extends PaymentProviderValidator {

	/**
	 * @throws ValidationException
	 */
	public function validateCreateSessionInput( array $params ): void {
		$required = [
			'validation_url',
			'domain_name'
		];

		$this->validateFields( $required, $params );
	}

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
			'first_name',
			'last_name'
		];

		$this->validateFields( $required, $params );
	}

}
