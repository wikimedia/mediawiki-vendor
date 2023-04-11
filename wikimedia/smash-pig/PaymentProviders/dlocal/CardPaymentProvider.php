<?php

namespace SmashPig\PaymentProviders\dlocal;

use SmashPig\Core\ApiException;
use SmashPig\PaymentProviders\IPaymentProvider;
use SmashPig\PaymentProviders\Responses\ApprovePaymentResponse;
use SmashPig\PaymentProviders\Responses\CreatePaymentResponse;

class CardPaymentProvider extends PaymentProvider implements IPaymentProvider {

	/**
	 * @param array $params
	 * @return CreatePaymentResponse
	 */
	public function createPayment( array $params ): CreatePaymentResponse {
		try {
			$this->validateCreatePaymentParams( $params );
			if ( !empty( $params['recurring_payment_token'] ) ) {
				$rawResponse = $this->api->makeRecurringCardPayment( $params );
			} else {
				$rawResponse = $this->api->cardAuthorizePayment( $params );
			}
			return DlocalCreatePaymentResponseFactory::fromRawResponse( $rawResponse );
		} catch ( ValidationException $validationException ) {
			$response = new CreatePaymentResponse();
			self::handleValidationException( $response, $validationException->getData() );
			return $response;
		} catch ( ApiException $apiException ) {
			return DlocalCreatePaymentResponseFactory::fromErrorResponse( $apiException->getRawErrors() );
		}
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
	 */
	public function approvePayment( array $params ): ApprovePaymentResponse {
		try {
			$this->validateApprovePaymentParams( $params );
			$rawResponse = $this->api->capturePayment( $params );
			return DlocalApprovePaymentResponseFactory::fromRawResponse( $rawResponse );
		} catch ( ValidationException $validationException ) {
			$response = new ApprovePaymentResponse();
			self::handleValidationException( $response, $validationException->getData() );
			return $response;
		} catch ( ApiException $apiException ) {
			return DlocalApprovePaymentResponseFactory::fromErrorResponse( $apiException->getRawErrors() );
		}
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
