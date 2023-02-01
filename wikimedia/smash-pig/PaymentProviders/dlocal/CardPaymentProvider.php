<?php

namespace SmashPig\PaymentProviders\dlocal;

use SmashPig\Core\ValidationError;
use SmashPig\PaymentData\FinalStatus;
use SmashPig\PaymentProviders\IPaymentProvider;
use SmashPig\PaymentProviders\Responses\ApprovePaymentResponse;
use SmashPig\PaymentProviders\Responses\CreatePaymentResponse;

class CardPaymentProvider extends PaymentProvider implements IPaymentProvider {

	/**
	 * @param array $params
	 * @return CreatePaymentResponse
	 * @throws \SmashPig\Core\ApiException
	 */
	public function createPayment( array $params ): CreatePaymentResponse {
		$response = new CreatePaymentResponse();
		$invalidParams = $this->validateParams( $params );
		$params['payment_method_id'] = "CARD";
		$params['payment_method_flow'] = "DIRECT";

		if ( count( $invalidParams ) === 0 ) {
			// transform is only called when required fields are present
			$params = $this->transformToApiParams( $params );
			$rawResponse = $this->api->authorizePayment( $params );
			$response->setRawResponse( $rawResponse );

			$rawStatus = $rawResponse['status'] ?? "";
			$response->setRawStatus( $rawStatus );
			$this->mapStatusAndAddErrorsIfAny( $response );

			if ( $response->isSuccessful() ) {
				$response->setGatewayTxnId( $rawResponse['id'] );
			}
		} else {
			foreach ( $invalidParams as $field ) {
				$response->addValidationError(
						new ValidationError( $field,
								null, [],
								'Invalid ' . $field )
				);
			}
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
	 * @throws \SmashPig\Core\ApiException
	 */
	public function approvePayment( array $params ): ApprovePaymentResponse {
		if ( $this->validateApprovePaymentParams( $params ) ) {
			$rawResponse = $this->api->capturePayment( $params );
			$approvePaymentResponse = DlocalApprovePaymentResponseFactory::fromRawResponse( $rawResponse );
		} else {
			$approvePaymentResponse = new ApprovePaymentResponse();
			$this->addApprovePaymentResponseValidationErrors( $params, $approvePaymentResponse );
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
	 *
	 * @return array invalid fields
	 */
	protected function validateParams( array $params ): array {
		$invalidFields = [];
		$requiredFields = [
			'amount',
			'currency',
			'country',
			'order_id',
			'payment_token',
			'first_name',
			'last_name',
			'email',
			'fiscal_number',
		];
		foreach ( $requiredFields as $field ) {
			if ( empty( $params[$field] ) ) {
				$invalidFields[] = $field;
			}
		}
		return $invalidFields;
	}

	/**
	 * @param array $params
	 * Convert the API request body to DLocal Request standards
	 *
	 * @return array
	 */
	protected function transformToApiParams( array $params ): array {
		$apiParams = [
			'amount' => $params['amount'],
			'currency' => $params['currency'],
			'country' => $params['country'],
			'payment_method_id' => $params['payment_method_id'],
			'payment_method_flow' => $params['payment_method_flow'],
			'order_id' => $params['order_id'],
			'card' => [
				'token' => $params['payment_token'],
				'capture' => false
			],
			'payer' => [
				'name' => $params['first_name'] . ' ' . $params['last_name'],
				'email' => $params['email'],
				'document' => $params['fiscal_number'],
			]
		];

		if ( array_key_exists( 'contact_id', $params ) ) {
			$apiParams['payer']['user_reference'] = $params['contact_id'];
		}

		if ( array_key_exists( 'user_ip', $params ) ) {
			$apiParams['payer']['ip'] = $params['user_ip'];
		}

		if ( array_key_exists( 'state_province', $params ) ) {
			$apiParams['address']['state'] = $params['state_province'];
		}

		if ( array_key_exists( 'city', $params ) ) {
			$apiParams['address']['city'] = $params['city'];
		}

		if ( array_key_exists( 'postal_code', $params ) ) {
			$apiParams['address']['zip_code'] = $params['postal_code'];
		}

		if ( array_key_exists( 'street_address', $params ) ) {
			$apiParams['address']['street'] = $params['street_address'];
		}

		if ( array_key_exists( 'street_number', $params ) ) {
			$apiParams['address']['number'] = $params['street_number'];
		}

		return $apiParams;
	}

	/**
	 * Confirm required parameters are set.
	 *
	 * 'gateway_txn_id' - Required
	 *
	 * @param array $params
	 * @return bool
	 */
	protected function validateApprovePaymentParams( array $params ): bool {
		return array_key_exists( 'gateway_txn_id', $params );
	}

	/**
	 * @param array $params
	 * @param ApprovePaymentResponse $approvePaymentResponse
	 * @return void
	 */
	protected function addApprovePaymentResponseValidationErrors( array $params, ApprovePaymentResponse $approvePaymentResponse ): void {
		$missingParams = $this->getMissingApprovePaymentParams( $params );
		foreach ( $missingParams as $missingParam ) {
			$approvePaymentResponse->addValidationError( new ValidationError( $missingParam ) );
		}
	}

	/**
	 * @param array $params
	 * @return array
	 */
	protected function getMissingApprovePaymentParams( array $params ): array {
		$requiredParams = [
			'gateway_txn_id'
		];

		return array_filter( $requiredParams, static function ( $requiredParam ) use ( $params ) {
			return !array_key_exists( $requiredParam, $params );
		} );
	}

}
