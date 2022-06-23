<?php

namespace SmashPig\PaymentProviders\Braintree;

use SmashPig\Core\ValidationError;
use SmashPig\PaymentData\FinalStatus;
use SmashPig\PaymentProviders\CreatePaymentResponse;

class PaypalPaymentProvider extends PaymentProvider {
	/**
	 * @param array $params
	 * Available params
	 *  * 'payment_token' (required)
	 *  * 'amount' (required)
	 *  * 'order_id'
	 * 	* 'currency' ?? TODO:
	 * @return CreatePaymentResponse
	 */
	public function createPayment( array $params ): CreatePaymentResponse {
		$successfulStatuses = [ FinalStatus::PENDING_POKE, FinalStatus::COMPLETE ];
		$params = $this->transformToApiParams( $params );
		$rawResponse = $this->api->chargePayment( $params );
		$response = new CreatePaymentResponse();
		$response->setRawResponse( $rawResponse );
		if ( !empty( $rawResponse['errors'] ) ) {
			$response->setSuccessful( false );
			$response->setStatus( FinalStatus::FAILED );
			foreach ( $rawResponse['errors'] as $error ) {
				$mappedError = $this->mapErrors( $error['extensions'], $error['message'] );
				if ( $mappedError instanceof ValidationError ) {
					$response->addValidationError( $mappedError );
				} else {
					$response->addErrors( $mappedError );
				}
			}
		} else {
			$response->setGatewayTxnId( $rawResponse['data']['chargePaymentMethod']['transaction']['id'] );
			$mappedStatus = ( new PaymentStatus() )->normalizeStatus( $rawResponse['data']['chargePaymentMethod']['transaction']['status'] );
			$response->setSuccessful( in_array( $mappedStatus, $successfulStatuses ) );
			$response->setStatus( $mappedStatus );
		}
		return $response;
	}

	/**
	 * @param array $params
	 * @return array
	 */
	protected function transformToApiParams( array $params ): array {
		$apiParams = [];
		if ( !empty( $params['payment_token'] ) ) {
			$apiParams['paymentMethodId'] = $params['payment_token'];
		} else {
			throw new \InvalidArgumentException( "payment_token is a required field" );
		}

		if ( !empty( $params['amount'] ) ) {
			$apiParams['transaction'] = [
				'amount' => $params['amount']
			];
		} else {
			throw new \InvalidArgumentException( "amount is a required field" );
		}

		if ( !empty( $params['order_id'] ) ) {
			$apiParams['transaction']['orderId'] = $params['order_id'];
		} else {
			throw new \InvalidArgumentException( "order_id is a required field" );
		}
		return $apiParams;
	}

}
