<?php

namespace SmashPig\PaymentProviders\Gravy\Validators;

use SmashPig\PaymentProviders\ValidationException;

trait ValidatorTrait {
	/**
	 * @throws ValidationException
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
