<?php

namespace SmashPig\PaymentProviders\Gravy\Validators;

use SmashPig\PaymentProviders\ValidationException;

/**
 * This trait contains the logic used for validating the fields in the validator class.
 */
trait ValidatorTrait {
	/**
	 * Checks the presence of the required fields in the params array and throws the
	 * ValidationException if necessary.
	 *
	 * @param array $requiredFields
	 * @param array $params
	 * @throws ValidationException
	 * @return void
	 */
	protected function validateFields( array $requiredFields, array $params ) {
		$invalidFields = [];
		foreach ( $requiredFields as $field ) {
			if ( empty( $params[$field] ) ) {
				$invalidFields[$field] = 'required';
			}
		}

		if ( count( $invalidFields ) ) {
			throw new ValidationException( 'Invalid input', $invalidFields );
		}
	}
}
