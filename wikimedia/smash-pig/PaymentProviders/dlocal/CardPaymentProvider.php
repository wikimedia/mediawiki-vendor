<?php

namespace SmashPig\PaymentProviders\dlocal;

use SmashPig\Core\ApiException;
use SmashPig\PaymentData\FinalStatus;
use SmashPig\PaymentProviders\IPaymentProvider;
use SmashPig\PaymentProviders\Responses\ApprovePaymentResponse;
use SmashPig\PaymentProviders\Responses\CreatePaymentResponse;

class CardPaymentProvider extends PaymentProvider implements IPaymentProvider {

	/**
	 * @param array $params
	 * @return CreatePaymentResponse
	 * @throws ApiException
	 */
	public function createPayment( array $params ): CreatePaymentResponse {
		$response = new CreatePaymentResponse();
		try {
			$this->validateCreatePaymentParams( $params );
			if ( !empty( $params['recurring_payment_token'] ) ) {
				$rawResponse = $this->api->makeRecurringPayment( $params );
			} else {
				$rawResponse = $this->api->authorizePayment( $params );
			}
			$response = DlocalCreatePaymentResponseFactory::fromRawResponse( $rawResponse );
		} catch ( ValidationException $validationException ) {
			$this->addPaymentResponseValidationErrors( $validationException->getData(), $response );
			$response->setStatus( FinalStatus::FAILED );
			$response->setSuccessful( false );
		}

		return $response;
	}

	/**
	 * Capture an authorized payment
	 *
	 * 'gateway_txn_id' - Required
	 * 'amount' - optional
	 * 'currency' - optional
	 * 'order_id' - optional
	 *
	 * @param array $params
	 * @return ApprovePaymentResponse
	 * @throws ApiException
	 */
	public function approvePayment( array $params ): ApprovePaymentResponse {
		try {
			$this->validateApprovePaymentParams( $params );
			$rawResponse = $this->api->capturePayment( $params );
			$approvePaymentResponse = DlocalApprovePaymentResponseFactory::fromRawResponse( $rawResponse );
		} catch ( ValidationException $validationException ) {
			$approvePaymentResponse = new ApprovePaymentResponse();
			$this->addPaymentResponseValidationErrors( $validationException->getData(), $approvePaymentResponse );
			$approvePaymentResponse->setSuccessful( false );
			$approvePaymentResponse->setStatus( FinalStatus::FAILED );
		}

		return $approvePaymentResponse;
	}

	/**
	 * @param array $params
	 * Need check for the following required params
	 * 'payment_token'
	 * 'amount'
	 * 'order_id'
	 * 'currency'
	 * 'first_name'
	 * 'last_name'
	 * 'email'
	 * 'fiscal_number'
	 * 'country',
	 * @throws ValidationException
	 */
	private function validateCreatePaymentParams( array $params ): void {
		if ( empty( $params['payment_token'] ) && empty( $params['recurring_payment_token'] ) ) {
			$invalidFields = [
				'payment_token' => 'required'
			];
			throw new ValidationException( "Invalid input", $invalidFields );
		}
		$requiredFields = [
			'amount',
			'currency',
			'country',
			'order_id',
			'first_name',
			'last_name',
			'email',
			'fiscal_number',
		];

		self::checkFields( $requiredFields, $params );
	}

	/**
	 * Confirm required parameters are set.
	 *
	 * 'gateway_txn_id' - Required
	 *
	 * @param array $params
	 * @return void
	 * @throws ValidationException
	 */
	protected function validateApprovePaymentParams( array $params ): void {
		$requiredFields = [ 'gateway_txn_id' ];
		self::checkFields( $requiredFields, $params );
	}

}
