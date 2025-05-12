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
			'AR', 'BR', 'CL', 'CO', 'ID', 'IN', 'MX', 'MY', 'PH', 'TH', 'ZA',
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
	abstract public function validateOneTimeCreatePaymentInput( array $params ): void;

	/**
	 * Resolves the type of validation for the create payment input depending on the transaction type.
	 *
	 * @param array $params
	 * @throws ValidationException
	 * @return void
	 */
	public function validateCreatePaymentInput( array $params ): void {
		// recurring charge is same across all methods
		if ( isset( $params['recurring_payment_token'] ) ) {
			$this->validateRecurringCreatePaymentInput( $params );
		} else {
			$this->validateOneTimeCreatePaymentInput( $params );
		}
	}

	/**
	 * Checks the recurring create payment input parameters for correctness and completeness.
	 *
	 * This method is the same for all payment methods on Gravy.
	 *
	 * @param array $params
	 * @throws ValidationException
	 * @return void
	 */
	public function validateRecurringCreatePaymentInput( array $params ): void {
		$defaultRequiredFields = [
			'recurring_payment_token',
			'amount',
			'currency',
			'country',
			'order_id',
			'email',
			'first_name',
			'last_name'
		];

		$required = array_merge(
			$defaultRequiredFields,
			$this->addCountrySpecificRequiredFields( $params )
		);

		$this->validateFields( $required, $params );
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
