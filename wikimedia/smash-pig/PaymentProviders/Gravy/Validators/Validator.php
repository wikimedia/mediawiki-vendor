<?php

namespace SmashPig\PaymentProviders\Gravy\Validators;

use SmashPig\Core\ProviderConfiguration;
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
			'country',
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
	public function validateRedirectCreatePaymentInput( array $params ): void {
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

	/**
	 * @throws ValidationException
	 */
	public function validateCreatePaymentFromTokenInput( array $params ): void {
		$required = [
			'recurring_payment_token',
			'processor_contact_id',
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

	/**
	 * @throws ValidationException
	 */
	public function validateGetPaymentDetailsInput( array $params ): void {
		$required = [
			'gateway_txn_id'
		];

		$this->validateFields( $required, $params );
	}

	/**
	 * @throws ValidationException
	 */
	public function validateGetRefundInput( array $params ): void {
		$required = [
			'gateway_refund_id',
		];

		$this->validateFields( $required, $params );
	}

	/**
	 * @throws ValidationException
	 */
	public function validateRefundInput( array $params ): void {
		$required = [
			'gateway_txn_id',
		];

		if ( isset( $params['amount'] ) && !empty( $params['amount'] ) ) {
			$required[] = 'currency';
		}

		$this->validateFields( $required, $params );
	}

	/**
	 * @throws ValidationException
	 */
	public function validateCancelPaymentInput( array $params ): void {
		$required = [
			'gateway_txn_id'
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
	public function validateWebhookEventHeader( array $params, ProviderConfiguration $config ): void {
		$required = [
			'AUTHORIZATION'
		];

		$this->validateFields( $required, $params );

		// Gr4vy currently only supports basic authentication for webhook security
		$base64_authorization_value = "Basic " . base64_encode( $config->val( "accounts/webhook/username" ) . ":" . $config->val( "accounts/webhook/password" ) );

		if ( $params["AUTHORIZATION"] != $base64_authorization_value ) {
			throw new ValidationException( "Invalid Authorisation header", [
				"AUTHORISATION" => 'invalid'
			] );
		}
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
