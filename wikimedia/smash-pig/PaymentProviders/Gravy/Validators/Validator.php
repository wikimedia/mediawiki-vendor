<?php

namespace SmashPig\PaymentProviders\Gravy\Validators;

use SmashPig\PaymentProviders\ValidationException;

class Validator {

	/**
	 * @throws ValidationException
	 */
	public function getDonorInputIsValid( array $params ) {
		$required = [
			'email',
		];

		$this->checkFields( $required, $params );
		return true;
	}

	/**
	 * @throws ValidationException
	 */
	public function createPaymentInputIsValid( array $params ) {
		$required = [
			'gateway_session_id',
			'amount',
			'currency',
			'order_id',
			'email',
			'first_name',
			'last_name'
		];

		$this->checkFields( $required, $params );
		return true;
	}

	/**
	 * @throws ValidationException
	 */
	public function approvePaymentInputIsValid( array $params ) {
		$required = [
			'gateway_txn_id',
			'amount'
		];

		$this->checkFields( $required, $params );
		return true;
	}

	/**
	 * @throws ValidationException
	 */
	public function createDonorInputIsValid( array $params ) {
		$required = [
			'first_name',
			'last_name',
			'email'
		];

		$this->checkFields( $required, $params );
		return true;
	}

	/**
	 * @throws ValidationException
	 */
	protected function checkFields( array $requiredFields, array $params ) {
		$invalidFields = [];
		foreach ( $requiredFields as $field ) {
			if ( empty( $params[$field] ) ) {
				$invalidFields[$field] = 'required';
			}
		}

		if ( count( $invalidFields ) ) {
			throw new ValidationException( "Invalid input", $invalidFields );
		}
	}

}
