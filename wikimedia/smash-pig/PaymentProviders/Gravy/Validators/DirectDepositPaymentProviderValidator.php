<?php

namespace SmashPig\PaymentProviders\Gravy\Validators;

use SmashPig\PaymentProviders\ValidationException;

/**
 * This class provides input validation for ACH redirect payment requests.
 */
class DirectDepositPaymentProviderValidator extends PaymentProviderValidator {
	/**
	 * Checks the one time ACH create payment input parameters for correctness and completeness.
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
}
