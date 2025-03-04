<?php

namespace SmashPig\PaymentProviders\Gravy\Validators;

use SmashPig\PaymentProviders\ValidationException;

class RedirectPaymentProviderValidator extends PaymentProviderValidator {
	/**
	 * @throws ValidationException
	 */
	public function validateOneTimeCreatePaymentInput( array $params ): void {
		$defaultRequiredFields = [
			'amount',
			'currency',
			'country',
			'order_id',
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
