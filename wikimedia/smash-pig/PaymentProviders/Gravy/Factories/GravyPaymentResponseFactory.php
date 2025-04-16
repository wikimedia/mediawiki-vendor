<?php

namespace SmashPig\PaymentProviders\Gravy\Factories;

use Psr\Log\LogLevel;
use SmashPig\Core\PaymentError;
use SmashPig\Core\ValidationError;
use SmashPig\PaymentData\ErrorCode;
use SmashPig\PaymentData\FinalStatus;
use SmashPig\PaymentProviders\Responses\PaymentProviderExtendedResponse;
use SmashPig\PaymentProviders\Responses\PaymentProviderResponse;

abstract class GravyPaymentResponseFactory {

	abstract protected static function createBasicResponse(): PaymentProviderResponse;

	/**
	 * @param mixed $rawResponse
	 * @return PaymentProviderResponse
	 */
	public static function fromNormalizedResponse( array $response ): PaymentProviderResponse {
		$paymentProviderResponse = static::createBasicResponse();

		$rawResponse = $response['raw_response'] ?? [];
		$isSuccessful = $response['is_successful'];

		$paymentProviderResponse->setRawResponse( $rawResponse );
		$paymentProviderResponse->setNormalizedResponse( $response );
		$paymentProviderResponse->setStatus( $response['status'] );
		$paymentProviderResponse->setSuccessful( $isSuccessful );
		if ( static::isFailedTransaction( $paymentProviderResponse->getStatus() ) ) {
			static::addPaymentFailureError( $paymentProviderResponse, $response['message'] . ':' . $response['description'], $response['code'] );
			return $paymentProviderResponse;
		}
		$paymentProviderResponse->setRawStatus( $response['raw_status'] ?? '' );
		static::decorateResponse( $paymentProviderResponse, $response );
		return $paymentProviderResponse;
	}

	/**
	 * @param PaymentProviderResponse $paymentResponse
	 * @param array $error
	 * @return void
	 */
	public static function handleValidationException( PaymentProviderResponse $paymentResponse, array $error ): void {
		self::addPaymentValidationErrors( $paymentResponse, $error );
		$paymentResponse->setStatus( FinalStatus::FAILED );
		$paymentResponse->setSuccessful( false );
	}

	/**
	 * @param PaymentProviderResponse $paymentResponse
	 * @param string $error
	 * @return void
	 */
	public static function handleException( PaymentProviderResponse $paymentResponse, ?string $error = '', ?string $errorCode = null ): void {
		self::addPaymentFailureError( $paymentResponse, $error, $errorCode );
		$paymentResponse->setStatus( FinalStatus::FAILED );
		$paymentResponse->setSuccessful( false );
	}

	protected static function setBackendProcessorAndId(
		PaymentProviderExtendedResponse $paymentResponse, array $normalizedResponse
	) {
		$paymentResponse->setBackendProcessor( $normalizedResponse['backend_processor'] ?? null );
		$paymentResponse->setBackendProcessorTransactionId(
			$normalizedResponse['backend_processor_transaction_id'] ?? null
		);
	}

	protected static function setPaymentOrchestrationReconciliationId(
		PaymentProviderExtendedResponse $paymentResponse,
		array $normalizedResponse
	): void {
		$paymentResponse->setPaymentOrchestratorReconciliationId( $normalizedResponse['payment_orchestrator_reconciliation_id'] );
	}

	/**
	 * @param string $status
	 * @return bool
	 */
	protected static function isFailedTransaction( string $status ): bool {
		return $status === FinalStatus::FAILED;
	}

	/**
	 * @param PaymentProviderResponse $paymentResponse
	 * @param string|null $statusDetail
	 * @param string|null $statusCode
	 * @return void
	 */
	protected static function addPaymentFailureError( PaymentProviderResponse $paymentResponse, ?string $statusDetail = 'Unknown error', ?string $errorCode = null ): void {
		$paymentResponse->setSuccessful( false );
		$paymentResponse->addErrors(
			new PaymentError(
				$errorCode ?? ErrorCode::UNKNOWN,
				$statusDetail,
				LogLevel::ERROR
			)
		);
	}

	/**
	 * @param PaymentProviderResponse $paymentResponse
	 * @param array $params
	 * @return void
	 */
	protected static function addPaymentValidationErrors(
		PaymentProviderResponse $paymentResponse, array $params
	): void {
		foreach ( $params as $param => $message ) {
			$paymentResponse->addValidationError(
				new ValidationError( $param, null, [], $message )
			);
		}
	}

	/**
	 * @param PaymentProviderResponse $paymentResponse
	 * @param array $rawResponse
	 */
	protected static function decorateResponse( PaymentProviderResponse $paymentResponse, array $normalizedResponse ): void {
		// Default behavior is to do nothing here, but child classes can override it.
	}
}
