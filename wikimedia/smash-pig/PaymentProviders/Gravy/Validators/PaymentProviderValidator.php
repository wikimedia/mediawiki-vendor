<?php

namespace SmashPig\PaymentProviders\Gravy\Validators;

use SmashPig\PaymentProviders\ValidationException;

/**
 * This base abstract class contains common validator logic for all payment method types.
 */
abstract class PaymentProviderValidator {
	use ValidatorTrait;

	/**
	 * For some countries, additional fields are required so we check
	 * the country code and add the required fields accordingly to our
	 * validation checks.
	 */
	private const FIELD_COUNTRY_REQUIREMENTS = [
		'fiscal_number' => [
			'AR', 'BR'
		],
	];

	/**
	 * Checks the one time create payment input parameters for correctness and completeness.
	 *
	 * Each payment method type has specific requirements, as such this function should be defined
	 * in each Provider class to ensure the parameters are complete and correct.
	 *
	 * @param array $params
	 * @throws ValidationException
	 * @return void
	 */
	public function validateOneTimeCreatePaymentInput( array $params ): void {
		$defaultRequiredFields = [
			'amount',
			'currency',
			'country',
			'order_id',
		];

		$this->validateFields( $defaultRequiredFields, $params );

		$amount = $params['amount'] ?? null;
		// Check if amount is set and is a positive number
		if ( !is_numeric( $amount ) || (float)$amount <= 0 ) {
			throw new ValidationException( 'Invalid amount. Amount must be numeric and a positive number.', [
				'amount' => "Invalid amount: $amount",
			] );
		}
	}

	/**
	 * Resolves the type of validation for the create payment input depending on the transaction type.
	 *
	 * @param array &$params
	 * @throws ValidationException
	 * @return void
	 */
	public function validateCreatePaymentInput( array &$params ): void {
		// recurring charge is same across all methods
		if ( isset( $params['recurring_payment_token'] ) ) {
			$this->validateRecurringCreatePaymentInput( $params );
		} else {
			$this->validateOneTimeCreatePaymentInput( $params );
		}
	}

	public function validateProcessorContactId( array &$params ): void {
		// avoid processor_contact_id if not uuid
		if ( !empty( $params['processor_contact_id'] ) &&
			!preg_match(
				'/^[0-9a-f]{8}-?[0-9a-f]{4}-?4[0-9a-f]{3}-?[89ab][0-9a-f]{3}-?[0-9a-f]{12}$/i',
				$params['processor_contact_id']
			)
		) {
			$params['processor_contact_id'] = null;
		}
	}

	/**
	 *
	 * Fixes missing first or last name by splitting multi-word strings.
	 *
	 * @param array &$params
	 * @return void
	 * @throws ValidationException
	 */
	public function recurringNameCheck( array &$params ): void {
		if ( empty( $params['first_name'] ) || empty( $params['last_name'] ) ) {
			// Find which field has the data we might split
			$sourceField = !empty( $params['last_name'] ) ? 'last_name' : 'first_name';

			if ( !empty( $params[$sourceField] ) ) {
				$raw = trim( $params[$sourceField] );
				if ( str_contains( $raw, ' ' ) ) {
					$parts = explode( ' ', $raw, 2 );
				} elseif ( str_contains( $raw, '.' ) ) {
					$parts = explode( '.', $raw, 2 );
				} else {
					$parts = [ $raw ];
				}

				if ( count( $parts ) > 1 ) {
					$params['first_name'] = $parts[0];
					$params['last_name']  = $parts[1];
				} elseif ( count( $parts ) === 1 ) {
					// Only one part of name exists; use it as first_name and drop last_name
					$params['first_name'] = $parts[0]; // use firstname for ty email
					unset( $params['last_name'] );
				}
			} else {
				$params['first_name'] = null;
				unset( $params['last_name'] );
			}
		}
	}

	/**
	 * Checks the recurring create payment input parameters for correctness and completeness.
	 *
	 * This method is the same for all payment methods on Gravy.
	 *
	 * @param array &$params
	 * @throws ValidationException
	 * @return void
	 */
	public function validateRecurringCreatePaymentInput( array &$params ): void {
		$defaultRequiredFields = [
			'recurring_payment_token',
			'amount',
			'currency',
			'country',
			'order_id',
			'email'
		];

		$required = array_merge(
			$defaultRequiredFields,
			$this->addCountrySpecificRequiredFields( $params )
		);

		$this->validateFields( $required, $params );
		// T424766 recurring from third party might have missing first name or last name
		$this->recurringNameCheck( $params );
		// T431327 uuid should be the only format for processor_contact_id
		$this->validateProcessorContactId( $params );
	}

	/**
	 * Checks the payment status request input parameters for correctness and completeness.
	 *
	 * This method is the same for all payment methods on Gravy.
	 *
	 * @param array $params
	 * @throws ValidationException
	 * @return void
	 */
	public function validateGetLatestPaymentStatusInput( array $params ): void {
		$required = [
			'gateway_txn_id'
		];

		$this->validateFields( $required, $params );
	}

	/**
	 * Checks the refund details request input parameters for correctness and completeness.
	 *
	 * This method is the same for all payment methods on Gravy.
	 *
	 * @param array $params
	 * @throws ValidationException
	 * @return void
	 */
	public function validateGetRefundInput( array $params ): void {
		$required = [
			'gateway_refund_id',
		];

		$this->validateFields( $required, $params );
	}

	/**
	 * Checks the report execution request input parameters for correctness and completeness.
	 *
	 * This method is the same for all payment methods on Gravy.
	 *
	 * @param array $params
	 * @throws ValidationException
	 * @return void
	 */
	public function validateGetReportExecutionInput( array $params ): void {
		$required = [
			'report_execution_id'
		];

		$this->validateFields( $required, $params );
	}

	/**
	 * Checks the generate report request input parameters for correctness and completeness.
	 *
	 * This method is the same for all payment methods on Gravy.
	 *
	 * @param array $params
	 * @throws ValidationException
	 * @return void
	 */
	public function validateGenerateReportUrlInput( array $params ): void {
		$required = [
			'report_execution_id',
			'report_id'
		];

		$this->validateFields( $required, $params );
	}

	/**
	 * Checks the initiate refund request input parameters for correctness and completeness.
	 *
	 * This method is the same for all payment methods on Gravy.
	 *
	 * @param array $params
	 * @throws ValidationException
	 * @return void
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
	 * Checks the approve payment request input parameters for correctness and completeness.
	 *
	 * This method is the same for all payment methods on Gravy.
	 *
	 * @param array $params
	 * @throws ValidationException
	 * @return void
	 */
	public function validateApprovePaymentInput( array $params ): void {
		$required = [
			'gateway_txn_id',
			'currency',
			'amount'
		];

		$this->validateFields( $required, $params );
	}

	/**
	 * Checks the delete payment token request input parameters for correctness and completeness.
	 *
	 * This method is the same for all payment methods on Gravy.
	 *
	 * @param array $params
	 * @throws ValidationException
	 * @return bool
	 */
	public function validateDeletePaymentTokenInput( array $params ) {
		$required = [
			'recurring_payment_token'
		];

		$this->validateFields( $required, $params );
		return true;
	}

	/**
	 * Adds country-specific required fields based on the country code.
	 *
	 * @param array $params
	 * @return string[]
	 */
	protected function addCountrySpecificRequiredFields( array $params ): array {
		$countrySpecificFields = [];
		if ( isset( $params['country'] ) ) {
			$country = $params['country'];
			foreach ( self::FIELD_COUNTRY_REQUIREMENTS as $field => $countries ) {
				if ( in_array( $country, $countries, true ) ) {
					$countrySpecificFields[] = $field;
				}
			}
		}
		return $countrySpecificFields;
	}
}
