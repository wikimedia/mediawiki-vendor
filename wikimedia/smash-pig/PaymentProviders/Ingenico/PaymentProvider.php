<?php

namespace SmashPig\PaymentProviders\Ingenico;

use Psr\Log\LogLevel;
use SmashPig\Core\Context;
use SmashPig\Core\Logging\Logger;
use SmashPig\Core\Mapper\Mapper;
use SmashPig\Core\PaymentError;
use SmashPig\PaymentData\ErrorCode;
use SmashPig\PaymentData\FinalStatus;
use SmashPig\PaymentProviders\ICancelablePaymentProvider;
use SmashPig\PaymentProviders\IPaymentProvider;
use SmashPig\PaymentProviders\Responses\ApprovePaymentResponse;
use SmashPig\PaymentProviders\Responses\CancelPaymentResponse;
use SmashPig\PaymentProviders\Responses\CreatePaymentResponse;
use SmashPig\PaymentProviders\Responses\PaymentProviderResponse;

/**
 * Base class for Ingenico payments. Each payment product group should get
 * a concrete subclass implementing PaymentProvider
 */
abstract class PaymentProvider implements IPaymentProvider, ICancelablePaymentProvider {

	/**
	 * @var Api
	 */
	protected $api;

	protected $providerConfiguration;

	/**
	 * PaymentProvider constructor.
	 *
	 * @param array $options
	 *
	 * @throws \SmashPig\Core\ConfigurationKeyException
	 */
	public function __construct( $options = [] ) {
		// FIXME: provide objects in constructor
		$this->providerConfiguration = Context::get()->getProviderConfiguration();
		$this->api = $this->providerConfiguration->object( 'api' );
	}

	/**
	 * @param array $params
	 *
	 * @return CreatePaymentResponse
	 * @throws \SmashPig\Core\ApiException
	 * @throws \SmashPig\Core\ConfigurationKeyException
	 */
	public function createPayment( array $params ): CreatePaymentResponse {
		$path = "payments";
		$mapConfig = $this->providerConfiguration->val( 'maps/create-payment' );
		$createPaymentParams = Mapper::map(
			$params,
			$mapConfig['path'],
			$mapConfig['transformers'],
			null,
			true
		);

		$rawResponse = $this->api->makeApiCall( $path, 'POST', $createPaymentParams, true );
		$response = new CreatePaymentResponse();
		$this->prepareResponseObject( $response, $rawResponse );

		return $response;
	}

	/**
	 * TODO: make this return a normalized PaymentStatusResponse or the like
	 *
	 * @param string $gatewayTxnId The full Ingenico payment ID
	 * @return array
	 * @throws \SmashPig\Core\ApiException
	 */
	public function getPaymentStatus( $gatewayTxnId ) {
		// Our gateway_txn_id corresponds to paymentId in Ingenico's documentation.
		$path = "payments/$gatewayTxnId";
		$response = $this->api->makeApiCall( $path, 'GET' );
		$this->addPaymentStatusErrorsIfPresent( $response );
		return $response;
	}

	/**
	 * For cards, this corresponds to a 'capture'
	 *
	 * @param array $params Ingenico only needs key 'gateway_txn_id' set to the Ingenico payment ID
	 * @return ApprovePaymentResponse
	 * @throws \SmashPig\Core\ApiException
	 */
	public function approvePayment( array $params ): ApprovePaymentResponse {
		// Our gateway_txn_id corresponds to paymentId in Ingenico's documentation.
		$gatewayTxnId = $params['gateway_txn_id'];

		$path = "payments/$gatewayTxnId/approve";
		$rawResponse = $this->api->makeApiCall( $path, 'POST', [] );

		$response = new ApprovePaymentResponse();
		$this->prepareResponseObject( $response, $rawResponse, [ FinalStatus::COMPLETE ] );

		return $response;
	}

	/**
	 *
	 * @param string $gatewayTxnId
	 * @return CancelPaymentResponse
	 * @throws \SmashPig\Core\ApiException
	 */
	public function cancelPayment( $gatewayTxnId ): CancelPaymentResponse {
		// Our gateway_txn_id corresponds to paymentId in Ingenico's documentation.
		$path = "payments/$gatewayTxnId/cancel";
		$rawResponse = $this->api->makeApiCall( $path, 'POST' );
		$this->addPaymentStatusErrorsIfPresent( $rawResponse, $rawResponse['payment'] );

		$response = new CancelPaymentResponse();
		$this->prepareResponseObject( $response, $rawResponse, [ FinalStatus::CANCELLED ] );
		return $response;
	}

	/**
	 * @param string $gatewayTxnId The full Ingenico payment ID
	 * @return array
	 * @throws \SmashPig\Core\ApiException
	 */
	public function tokenizePayment( $gatewayTxnId ) {
		// Our gateway_txn_id corresponds to paymentId in Ingenico's documentation.
		$path = "payments/$gatewayTxnId/tokenize";
		$response = $this->api->makeApiCall( $path, 'POST' );
		return $response;
	}

	/**
	 * Check for the presence of payment status response errors and if present
	 * log and add them to the top-level response.
	 *
	 * @param array $response
	 * @param array|null $paymentResponse
	 */
	protected function addPaymentStatusErrorsIfPresent( &$response, $paymentResponse = null ) {
		if ( $paymentResponse === null ) {
			$paymentResponse = $response;
		}

		if ( $this->hasPaymentStatusErrors( $paymentResponse ) ) {
			$response['errors'] = $this->getPaymentStatusErrors( $paymentResponse );
			$this->logPaymentStatusErrors( $response['errors'] );
		}
	}

	/**
	 * @param array $paymentResponse
	 *
	 * @return bool
	 */
	protected function hasPaymentStatusErrors( $paymentResponse ) {
		if ( isset( $paymentResponse['statusOutput'] ) &&
			!empty( $paymentResponse['statusOutput']['errors'] ) ) {
			return true;
		} else {
			return false;
		}
	}

	/**
	 * This is the first step in refunding a payment. You will need to use
	 * the ID from the result of this method and call approveRefund in order
	 * for the donor to actually get their money back.
	 * API call is documented at
	 * https://epayments-api.developer-ingenico.com/s2sapi/v1/en_US/java/payments/refund.html#payments-refund
	 *
	 * @param string $gatewayTxnId The full Ingenico payment ID
	 * @param array $params needs these keys set:
	 *  currency,
	 *  amount (in major units, e.g. dollars),
	 *  first_name,
	 *  last_name,
	 *  order_id,
	 *  country
	 * @return array
	 * @throws \SmashPig\Core\ApiException
	 * @throws \SmashPig\Core\ConfigurationKeyException
	 */
	public function createRefund( $gatewayTxnId, $params ) {
		// Our gateway_txn_id corresponds to paymentId in Ingenico's documentation.
		$path = "payments/$gatewayTxnId/refund";
		$mapConfig = $this->providerConfiguration->val( 'maps/refund-payment' );
		$createRefundParams = Mapper::map(
			$params,
			$mapConfig['path'],
			$mapConfig['transformers'],
			null,
			true
		);
		$response = $this->api->makeApiCall( $path, 'POST', $createRefundParams, true );
		$this->addPaymentStatusErrorsIfPresent( $response );
		return $response;
	}

	/**
	 * This is the second step to refunding a payment, documented at
	 * https://epayments-api.developer-ingenico.com/s2sapi/v1/en_US/java/refunds/approve.html#refunds-approve
	 *
	 * Note that the REST API accepts an 'amount' parameter in case you want to
	 * approve a different amount, but we have omitted that possibility.
	 *
	 * @param string $refundId the ID generated by createRefund
	 * @return array with errors if an error happened, otherwise empty
	 * @throws \SmashPig\Core\ApiException
	 */
	public function approveRefund( $refundId ) {
		$path = "refunds/$refundId/approve";
		// Need to POST something, even just an empty array
		$response = $this->api->makeApiCall( $path, 'POST', [] );
		return $response;
	}

	/**
	 * Currently we send these back verbatim to DonationInterface
	 *
	 * In future we might map these to
	 * https://github.com/Ingenico-ePayments/connect-sdk-php/blob/master/src/Ingenico/Connect/Sdk/Domain/Errors/Definitions/APIError.php
	 *
	 * @param array $paymentResponse
	 *
	 * @return array|bool
	 */
	protected function getPaymentStatusErrors( $paymentResponse ) {
		if ( isset( $paymentResponse['statusOutput'] ) &&
			!empty( $paymentResponse['statusOutput']['errors'] ) ) {
			return $paymentResponse['statusOutput']['errors'];
		} else {
			return false;
		}
	}

	/**
	 * @param array $errors
	 */
	protected function logPaymentStatusErrors( $errors ) {
		foreach ( $errors as $error ) {
			$logMessage = "Error code {$error['code']}: {$error['message']}.";
			Logger::warning( $logMessage );
		}
	}

	/**
	 * Maps errors and other properties from $rawResponse to $response
	 *
	 * @param PaymentProviderResponse $response
	 * @param array $rawResponse
	 */
	protected function prepareResponseObject(
		PaymentProviderResponse $response,
		array $rawResponse,
		array $sucessfulStatuses = [ FinalStatus::COMPLETE, FinalStatus::PENDING_POKE ]
	) {
		$response->setRawResponse( $rawResponse );
		if ( isset( $rawResponse['errors'] ) ) {
			$response->addErrors(
				$this->mapErrors( $rawResponse['errors'] )
			);
			$response->setSuccessful( false );
		}

		if ( isset( $rawResponse['payment'] ) ) {
			$rootPaymentNode = $rawResponse['payment'];
		} elseif ( isset( $rawResponse['paymentResult']['payment'] ) ) {
			$rootPaymentNode = $rawResponse['paymentResult']['payment'];
		} elseif ( isset( $rawResponse['createdPaymentOutput']['payment'] ) ) {
			$rootPaymentNode = $rawResponse['createdPaymentOutput']['payment'];
		} else {
			if ( $response->hasErrors() ) {
				// There is already a top-level error code which may have prevented
				// any payment creation. No need to add another error.
				return;
			}
			$responseError = 'payment element missing from Ingenico response.';
			$response->addErrors(
				new PaymentError(
					ErrorCode::MISSING_REQUIRED_DATA,
					$responseError,
					LogLevel::ERROR
				)
			);
			$response->setSuccessful( false );
			Logger::debug( $responseError, $rawResponse );
			return;
		}
		// map trxn id
		if ( !empty( $rootPaymentNode['id'] ) ) {
			$response->setGatewayTxnId( $rootPaymentNode['id'] );
		} else {
			$message = 'Unable to map Ingenico gateway transaction ID';
			$response->addErrors(
				new PaymentError(
					ErrorCode::MISSING_TRANSACTION_ID,
					$message,
					LogLevel::ERROR
				)
			);
			$response->setSuccessful( false );
			Logger::debug( $message, $rawResponse );
		}
		// map status
		if ( !empty( $rootPaymentNode['status'] ) ) {
			$rawStatus = $rootPaymentNode['status'];
			$response->setRawStatus( $rawStatus );
			try {
				$status = ( new PaymentStatus() )->normalizeStatus( $rawStatus );
				$response->setStatus( $status );
				$success = in_array( $status, $sucessfulStatuses );
				$response->setSuccessful( $success );
			} catch ( \Exception $ex ) {
				$response->addErrors(
					new PaymentError(
						ErrorCode::UNEXPECTED_VALUE,
						$ex->getMessage(),
						LogLevel::ERROR
					)
				);
				$response->setSuccessful( false );
				Logger::debug( 'Unable to map Ingenico status', $rawResponse );
			}
		} else {
			Logger::debug( 'Unable to map Ingenico status', $rawResponse );
		}
		// map errors
		if ( !empty( $rootPaymentNode['statusOutput']['errors'] ) ) {
			$response->addErrors( $this->mapErrors( $rootPaymentNode['statusOutput']['errors'] ) );
		}
	}

	/**
	 * @param array $errors
	 * @return PaymentError[]
	 */
	protected function mapErrors( $errors ) {
		$errorMap = [
			'20000000' => ErrorCode::MISSING_REQUIRED_DATA,
			// TODO: handle 400120 which is ErrorCode::DUPLICATE_ORDER_ID when the TXN is INSERT_ORDERWITHPAYMENT
			'400490' => ErrorCode::DUPLICATE_ORDER_ID,
			'300620' => ErrorCode::DUPLICATE_ORDER_ID,
			'430260' => ErrorCode::DECLINED_DO_NOT_RETRY,
			'430349' => ErrorCode::DECLINED_DO_NOT_RETRY,
			'430357' => ErrorCode::DECLINED_DO_NOT_RETRY,
			'430410' => ErrorCode::DECLINED_DO_NOT_RETRY,
			'430415' => ErrorCode::DECLINED_DO_NOT_RETRY,
			'430418' => ErrorCode::DECLINED_DO_NOT_RETRY,
			'430421' => ErrorCode::DECLINED_DO_NOT_RETRY,
			'430697' => ErrorCode::DECLINED_DO_NOT_RETRY,
			'485020' => ErrorCode::DECLINED_DO_NOT_RETRY,
			'4360022' => ErrorCode::DECLINED_DO_NOT_RETRY,
			'4360023' => ErrorCode::DECLINED_DO_NOT_RETRY,
			'430306' => ErrorCode::DECLINED_DO_NOT_RETRY,
			'430330' => ErrorCode::DECLINED_DO_NOT_RETRY,
			'430354' => ErrorCode::DECLINED_DO_NOT_RETRY,
			'430285' => ErrorCode::DECLINED,
			'430396' => ErrorCode::DECLINED,
			'430409' => ErrorCode::DECLINED,
			'430424' => ErrorCode::DECLINED,
			'430692' => ErrorCode::DECLINED,
			'11000400' => ErrorCode::SERVER_TIMEOUT,
			// TODO: handle 20001000 and 21000050 validation problems
		];
		$mappedErrors = [];
		foreach ( $errors as $error ) {
			if ( isset( $errorMap[$error['code']] ) ) {
				$mappedCode = $errorMap[$error['code']];
				$logLevel = LogLevel::INFO;
			} else {
				$mappedCode = ErrorCode::UNKNOWN;
				$logLevel = LogLevel::ERROR;
			}
			$mappedErrors[] = new PaymentError(
				$mappedCode,
				json_encode( $error ),
				$logLevel
			);
		}
		return $mappedErrors;
	}
}
