<?php

namespace SmashPig\PaymentProviders\Braintree;

use Psr\Log\LogLevel;
use SmashPig\Core\Context;
use SmashPig\Core\PaymentError;
use SmashPig\Core\ValidationError;
use SmashPig\PaymentData\DonorDetails;
use SmashPig\PaymentData\ErrorCode;
use SmashPig\PaymentData\FinalStatus;
use SmashPig\PaymentProviders\IPaymentProvider;
use SmashPig\PaymentProviders\Responses\ApprovePaymentResponse;
use SmashPig\PaymentProviders\Responses\CreatePaymentResponse;
use SmashPig\PaymentProviders\Responses\CreatePaymentSessionResponse;
use SmashPig\PaymentProviders\Responses\PaymentProviderResponse;
use SmashPig\PaymentProviders\Responses\RefundPaymentResponse;

class PaymentProvider implements IPaymentProvider {

	/**
	 * @var Api
	 */
	protected $api;

	public function __construct() {
		$config = Context::get()->getProviderConfiguration();
		$this->api = $config->object( 'api' );
	}

	/**
	 * @param array $params
	 * Need check params
	 * 'payment_token' || 'recurring_payment_token' (required)
	 * 'amount' (required)
	 * 'order_id' (required)
	 * 'currency'
	 * @return array invalid fields
	 */
	protected function getInvalidParams( array $params ): array {
		$invalidParams = [];
		if ( empty( $params['payment_token'] ) && empty( $params['recurring_payment_token'] ) ) {
			$invalidParams[] = 'payment_token';
		}
		if ( empty( $params['order_id'] ) ) {
			$invalidParams[] = 'order_id';
		}
		if ( empty( $params['amount'] ) ) {
			$invalidParams[] = 'amount';
		}
		// if recurring, then no device_data needed
		if ( empty( $params['device_data'] ) && empty( $params['recurring_payment_token'] ) ) {
			$invalidParams[] = 'device_data';
		}
		return $invalidParams;
	}

	/**
	 * @return CreatePaymentSessionResponse
	 */
	public function createPaymentSession(): CreatePaymentSessionResponse {
		$rawResponse = $this->api->createClientToken();
		$response = new CreatePaymentSessionResponse();
		$response->setRawResponse( $rawResponse );
		$response->setPaymentSession( $rawResponse['data']['createClientToken']['clientToken'] );
		return $response;
	}

	/**
	 * @param array $params
	 * Available params
	 * 'payment_token' (required)
	 * 'amount' (required)
	 * 'order_id'
	 * 'currency'
	 * 'device_data'
	 * @return CreatePaymentResponse
	 */
	public function createPayment( array $params ): CreatePaymentResponse {
		$invalidParams = $this->getInvalidParams( $params );
		$invalidCurrency = $this->getInvalidCurrency( $params['currency'] );
		if ( $invalidCurrency ) {
			$invalidParams[] = $invalidCurrency;
		}
		$response = new CreatePaymentResponse();
		// Get ValidationError from transformToApiParams if currency not supportedL255
		if ( count( $invalidParams ) > 0 ) {
			$response->setSuccessful( false );
			$response->setStatus( FinalStatus::FAILED );
			foreach ( $invalidParams as $invalidParam ) {
				$response->addValidationError(
					new ValidationError( $invalidParam,
						null, [],
						'Invalid ' . $invalidParam )
				);
			}
		} else {
			$transformParams = $this->transformToApiParams( $params );
			$rawResponse = $this->api->authorizePaymentMethod( $transformParams );
			$response->setRawResponse( $rawResponse );
			if ( !empty( $rawResponse['errors'] ) ) {
				$this->setResponseFailedWithErrors( $response, $rawResponse['errors'] );
			} else {
				$transaction = $rawResponse['data']['authorizePaymentMethod']['transaction'];
				// If it's recurring need to know when to set the token in setCreatePaymentSuccessfulResponseDetails
				if ( isset( $transformParams['transaction']['vaultPaymentMethodAfterTransacting'] ) ) {
					$transaction['recurring'] = true;
				}
				$this->setCreatePaymentSuccessfulResponseDetails( $transaction, $response, $params );
			}
		}
		return $response;
	}

	/**
	 * @param array $params
	 * Available params
	 * 'gateway_txn_id'
	 * 'payment_token' (required)
	 * 'amount' (required)
	 * 'order_id'
	 * 'currency' ?? TODO:
	 * @return ApprovePaymentResponse
	 */
	public function approvePayment( array $params ): ApprovePaymentResponse {
		$params = $this->transformToApiParams( $params, TransactionType::CAPTURE );
		$rawResponse = $this->api->captureTransaction( $params );
		$response = new ApprovePaymentResponse();
		$response->setRawResponse( $rawResponse );
		if ( !empty( $rawResponse['errors'] ) ) {
			$this->setResponseFailedWithErrors( $response, $rawResponse['errors'] );
		} else {
			$transaction = $rawResponse['data']['captureTransaction']['transaction'];
			$this->setApprovePaymentSuccessfulResponseDetails( $transaction, $response );
		}
		return $response;
	}

	/**
	 * @param string $errorClass
	 * @return int
	 * https://graphql.braintreepayments.com/guides/making_api_calls/#understanding-responses
	 */
	protected function getErrorCode( string $errorClass ): int {
		switch ( $errorClass ) {
			case 'INTERNAL':
				return ErrorCode::INTERNAL_ERROR;
			case 'NOT_FOUND':
				return ErrorCode::METHOD_NOT_FOUND;
			case 'NOT_IMPLEMENTED':
				return ErrorCode::ACCOUNT_MISCONFIGURATION;
			case 'RESOURCE_LIMIT':
				return ErrorCode::EXCEEDED_LIMIT;
			case 'SERVICE_AVAILABILITY':
				return ErrorCode::SERVER_TIMEOUT;
			case 'UNSUPPORTED_CLIENT':
				return ErrorCode::UNEXPECTED_VALUE;
			case 'AUTHORIZATION':
			case 'AUTHENTICATION':
				return ErrorCode::BAD_SIGNATURE;
			case 'VALIDATION':
				return ErrorCode::VALIDATION;
			default:
				return ErrorCode::UNKNOWN;
		}
	}

	/**
	 * @param array $error
	 * @param string|null $debugMessage
	 * @return PaymentError|ValidationError
	 */
	protected function mapErrors( array $error, string $debugMessage = null ) {
		$defaultCode = ErrorCode::UNKNOWN;

		/**
		 *  https://developer.paypal.com/braintree/docs/reference/general/validation-errors/all
		 */
		$errorMap = [
			'82901' => ErrorCode::ACCOUNT_MISCONFIGURATION,
			'82903' => ErrorCode::ACCOUNT_MISCONFIGURATION,
			'82904' => ErrorCode::ACCOUNT_MISCONFIGURATION,
			'92906' => ErrorCode::ACCOUNT_MISCONFIGURATION
		];
		$mappedCode = $defaultCode;
		$logLevel = LogLevel::ERROR;
		if ( isset( $error['legacyCode'] ) && in_array( $error['legacyCode'], $errorMap ) ) {
			$mappedCode = $errorMap[$error['legacyCode']];
		}
		if ( isset( $error['errorClass'] ) ) {
			$mappedCode = $this->getErrorCode( $error['errorClass'] );
		}
		if ( $mappedCode == ErrorCode::VALIDATION ) {
			$validationField = ValidationErrorMapper::getValidationErrorField( $error['inputPath'] );
			return new ValidationError( $validationField, null, [], $debugMessage );
		}

		return new PaymentError(
			$mappedCode,
			json_encode( array_merge( $error, [ "message" => $debugMessage ] ) ),
			$logLevel
		);
	}

	/**
	 * @param array $params
	 * required params like payment token, amount, and order id
	 * are checked before this function been called
	 * @param string|null $type
	 *
	 * @return array
	 */
	protected function transformToApiParams( array $params, string $type = null ): array {
		$apiParams = [];

		if ( $type === TransactionType::CAPTURE ) {
			if ( !empty( $params['gateway_txn_id'] ) ) {
				$apiParams['transactionId'] = $params['gateway_txn_id'];
				return $apiParams;
			} else {
				throw new \InvalidArgumentException( "gateway_txn_id is a required field" );
			}
		}

		// use the set recurring payment token as the payment_token for subsequent recurring charges
		if ( !empty( $params['recurring_payment_token'] ) ) {
			$params['payment_token'] = $params['recurring_payment_token'];
		}

		$apiParams['paymentMethodId'] = $params['payment_token'];

		$apiParams['transaction'] = [
			'amount' => $params['amount'],
			'riskData' => [
				"deviceData" => $params['device_data'] ?? '{}', // do device_data then it's recurring donation
			],
			'customerDetails' => [
				'email' => $params['email'], // for venmo
				'phoneNumber' => $params['phone'] ?? ''
			],
			'customFields' => [
				"name" => "fullname",
				"value" => $params['first_name'] . ' ' . $params['last_name'],
			]
		];

		$this->indicateMerchant( $params, $apiParams );

		$apiParams['transaction']['orderId'] = $params['order_id'];

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

	protected function setCreatePaymentSuccessfulResponseDetails( array $transaction, CreatePaymentResponse $response, array $params ) {
		$successfulStatuses = [ FinalStatus::PENDING_POKE, FinalStatus::COMPLETE ];
		$mappedStatus = ( new PaymentStatus() )->normalizeStatus( $transaction['status'] );
		$response->setSuccessful( in_array( $mappedStatus, $successfulStatuses ) );
		$response->setGatewayTxnId( $transaction['id'] );
		$donorDetails = new DonorDetails();
		$donorDetails->setFirstName( $transaction['paymentMethodSnapshot']['payer']['firstName'] ?? $params['first_name'] );
		$donorDetails->setLastName( $transaction['paymentMethodSnapshot']['payer']['lastName'] ?? $params['last_name'] );
		$donorDetails->setEmail( $transaction['paymentMethodSnapshot']['payer']['email'] ?? $params['email'] );
		$donorDetails->setPhone( $transaction['paymentMethodSnapshot']['payer']['phone'] ?? $params['phone'] ?? null );
		// additional data for venmo if customer id exist
		if ( !empty( $params['customer_id'] ) ) {
			$donorDetails->setCustomerId( $transaction['paymentMethodSnapshot']['payer']['customer_id'] ?? $params['customer_id'] );
			$donorDetails->setUserName( $transaction['paymentMethodSnapshot']['payer']['user_name'] ?? $params['user_name'] );
		}
		$response->setDonorDetails( $donorDetails );
		// The recurring token (vault) is the id of paymentMethod
		if ( isset( $transaction['recurring'] ) && $transaction['paymentMethod']['id'] ) {
			$response->setRecurringPaymentToken( $transaction['paymentMethod']['id'] );
		}
		$response->setStatus( $mappedStatus );
	}

	protected function setApprovePaymentSuccessfulResponseDetails( array $transaction, ApprovePaymentResponse $response ) {
		$successfulStatuses = [ FinalStatus::COMPLETE ];
		$mappedStatus = ( new PaymentStatus() )->normalizeStatus( $transaction['status'] );
		$response->setSuccessful( in_array( $mappedStatus, $successfulStatuses ) );
		$response->setGatewayTxnId( $transaction['id'] );
		$response->setStatus( $mappedStatus );
	}

	/**
	 * if error, to response add errors and set it to not success with failed status
	 * @param PaymentProviderResponse $response
	 * @param array $errors
	 *
	 * @return void
	 */
	protected function setResponseFailedWithErrors(
		PaymentProviderResponse $response,
		array $errors
	) {
		$response->setSuccessful( false );
		$response->setStatus( FinalStatus::FAILED );
		foreach ( $errors as $error ) {
			if ( isset( $error['extensions'] ) ) {
				$mappedError = $this->mapErrors( $error['extensions'], $error['message'] );
				if ( $mappedError instanceof ValidationError ) {
					$response->addValidationError( $mappedError );
				} else {
					$response->addErrors( $mappedError );
				}
			}
		}
	}

	/**
	 * @param array $params
	 * if refundTransaction, then success
	 * @return RefundPaymentResponse
	 */
	public function refundPayment( array $params ): RefundPaymentResponse {
		$rawResponse = $this->api->refundPayment( $params );
		$response = new RefundPaymentResponse();
		$response->setRawResponse( $rawResponse );
		if ( !empty( $rawResponse['errors'] ) ) {
			$this->setResponseFailedWithErrors( $response, $rawResponse['errors'] );
		} else {
			$detail = $rawResponse['data']['refundTransaction'][ 'refund' ];
			$response->setRawStatus( $detail[ 'status' ] );
			$mappedStatus = ( new PaymentStatus() )->normalizeStatus( $detail['status'] );
			$response->setStatus( $mappedStatus );
			$response->setSuccessful( $response->getStatus() === FinalStatus::COMPLETE );
			if ( !$response->isSuccessful() ) {
				// look message from status history and add to error message
				if ( isset( $detail['statusHistory'] ) && !empty( $detail['statusHistory'][0]['processorResponse']['message'] ) ) {
					$response->addErrors( $this->mapErrors( [], $detail['statusHistory'][0]['processorResponse']['message'] ) );
				}
			}
		}

		return $response;
	}
}
