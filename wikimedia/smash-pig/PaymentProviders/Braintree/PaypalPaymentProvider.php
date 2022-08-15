<?php

namespace SmashPig\PaymentProviders\Braintree;

use SmashPig\Core\ValidationError;
use SmashPig\PaymentData\FinalStatus;
use SmashPig\PaymentProviders\ApprovePaymentResponse;
use SmashPig\PaymentProviders\CreatePaymentResponse;
use SmashPig\PaymentProviders\DonorDetails;

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
		$params = $this->transformToApiParams( $params );
		$rawResponse = $this->api->authorizePayment( $params );
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
			$transaction = $rawResponse['data']['authorizePaymentMethod']['transaction'];
			// If it's recurring need to know when to set the token in setCreatePaymentSuccessfulResponseDetails
			if ( isset( $params['transaction']['vaultPaymentMethodAfterTransacting'] ) ) {
				$transaction['recurring'] = true;
			}
			$this->setCreatePaymentSuccessfulResponseDetails( $transaction, $response );
		}
		return $response;
	}

	/**
	 * @param array $params
	 * Available params
	 *  * 'gateway_txn_id'
	 *  * 'payment_token' (required)
	 *  * 'amount' (required)
	 *  * 'order_id'
	 * 	* 'currency' ?? TODO:
	 * @return ApprovePaymentResponse
	 */
	public function approvePayment( array $params ): ApprovePaymentResponse {
		$params = $this->transformToApiParams( $params, TransactionType::CAPTURE );
		$rawResponse = $this->api->captureTransaction( $params );
		$response = new ApprovePaymentResponse();
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
			$transaction = $rawResponse['data']['captureTransaction']['transaction'];
			$this->setApprovePaymentSuccessfulResponseDetails( $transaction, $response );
		}
		return $response;
	}

	/**
	 * @param array $params
	 * @param string|null $type
	 * @return array
	 */
	protected function transformToApiParams( array $params, string $type = null ): array {
		$apiParams = [];

		// use the set recurring payment token as the payment_token for subsequent recurring charges
		if ( !empty( $params['recurring_payment_token'] ) ) {
			$params['payment_token'] = $params['recurring_payment_token'];
		}

		if ( $type === TransactionType::CAPTURE ) {
			if ( !empty( $params['gateway_txn_id'] ) ) {
				$apiParams['transactionId'] = $params['gateway_txn_id'];
				return $apiParams;
			} else {
				throw new \InvalidArgumentException( "gateway_txn_id is a required field" );
			}
		}

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

		// Vaulting - saving the payment so we can use it for recurring charges
		// Options for when to vault
		// https://graphql.braintreepayments.com/reference/#enum--vaultpaymentmethodcriteria
		// Only want to vault on the initial authorize call where recurring=true
		// Don't want to vault when charging a subsequent recurring payment, these have installment=recurring
		$isRecurring = $params['recurring'] ?? '';
		$installment = $params['installment'] ?? '';
		if ( $installment != 'recurring' && $isRecurring ) {
			$apiParams['transaction']['vaultPaymentMethodAfterTransacting']['when'] = "ON_SUCCESSFUL_TRANSACTION";
		}

		return $apiParams;
	}

	protected function setCreatePaymentSuccessfulResponseDetails( array $transaction, CreatePaymentResponse &$response ) {
		$successfulStatuses = [ FinalStatus::PENDING_POKE, FinalStatus::COMPLETE ];
		$mappedStatus = ( new PaymentStatus() )->normalizeStatus( $transaction['status'] );
		$response->setSuccessful( in_array( $mappedStatus, $successfulStatuses ) );
		$response->setGatewayTxnId( $transaction['id'] );
		if ( isset( $transaction['paymentMethodSnapshot']['payer'] ) ) {
			$payer = $transaction['paymentMethodSnapshot']['payer'];
			$donorDetails = new DonorDetails();
			if ( isset( $payer['firstName'] ) ) {
				$donorDetails->setFirstName( $payer['firstName'] );
			}
			if ( isset( $payer['lastName'] ) ) {
				$donorDetails->setLastName( $payer['lastName'] );
			}
			if ( isset( $payer['email'] ) ) {
				$donorDetails->setEmail( $payer['email'] );
			}
			if ( isset( $payer['phone'] ) ) {
				$donorDetails->setPhone( $payer['phone'] );
			}
			$response->setDonorDetails( $donorDetails );
		}

		// The recurring token (vault) is the id of paymentMethod
		if ( isset( $transaction['recurring'] ) ) {
			$response->setRecurringPaymentToken( $transaction['paymentMethod']['id'] );
		}
		$response->setStatus( $mappedStatus );
	}

	protected function setApprovePaymentSuccessfulResponseDetails( array $transaction, ApprovePaymentResponse &$response ) {
		$successfulStatuses = [ FinalStatus::COMPLETE ];
		$mappedStatus = ( new PaymentStatus() )->normalizeStatus( $transaction['status'] );
		$response->setSuccessful( in_array( $mappedStatus, $successfulStatuses ) );
		$response->setGatewayTxnId( $transaction['id'] );
		$response->setStatus( $mappedStatus );
	}
}
