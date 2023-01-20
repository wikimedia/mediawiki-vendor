<?php

namespace SmashPig\PaymentProviders\dlocal;

use SmashPig\Core\ValidationError;
use SmashPig\PaymentData\FinalStatus;
use SmashPig\PaymentProviders\Responses\CreatePaymentResponse;

class CardPaymentProvider extends PaymentProvider {

	/**
	 * @param array $params
	 * Need check for the following required params
	 * 'payment_token'
	 * 'amount' (required)
	 * 'order_id' (required)
	 * 'currency'
	 * 'payment_method'
	 * 'payment_submethod'
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
				'payment_method',
				'payment_submethod',
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
				'payment_method_id' => $params['payment_method'],
				'payment_method_flow' => $params['payment_submethod'],
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

	public function createPayment( array $params ): CreatePaymentResponse {
		$response = new CreatePaymentResponse();
		$invalidParams = $this->validateParams( $params );

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
}
