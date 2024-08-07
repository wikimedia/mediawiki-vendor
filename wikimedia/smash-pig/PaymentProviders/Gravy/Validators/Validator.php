<?php

namespace SmashPig\PaymentProviders\Gravy\Validators;

use SmashPig\PaymentProviders\ValidationException;

class Validator {

	/**
	 * @throws ValidationException
	 */
	public function validateDonorInput( array $params ): void {
		$required = [
			'email',
		];

		$this->validateFields( $required, $params );
	}

	/**
	 * @throws ValidationException
	 */
	public function validateCreatePaymentInput( array $params ): void {
		$required = [
			'gateway_session_id',
			'amount',
			'currency',
			'order_id',
			'email',
			'first_name',
			'last_name'
		];

		$this->validateFields( $required, $params );
	}

	/**
	 * @throws ValidationException
	 */
	public function validateApprovePaymentInput( array $params ): void {
		$required = [
			'gateway_txn_id',
			'currency',
			'amount'
		];

		$this->validateFields( $required, $params );
	}

	public function validateDeletePaymentTokenInput( array $params ) {
		$required = [
			'recurring_payment_token'
		];

		$this->validateFields( $required, $params );
		return true;
	}

	/**
	 * @throws ValidationException
	 */
	public function validateCreateDonorInput( array $params ): void {
		$required = [
			'first_name',
			'last_name',
			'email'
		];

		$this->validateFields( $required, $params );
	}

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
			throw new ValidationException( "Invalid input", $invalidFields );
		}
	}

}
