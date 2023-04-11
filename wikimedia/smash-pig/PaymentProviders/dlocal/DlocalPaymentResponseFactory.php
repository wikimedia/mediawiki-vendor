<?php

namespace SmashPig\PaymentProviders\dlocal;

use Psr\Log\LogLevel;
use SmashPig\Core\Logging\Logger;
use SmashPig\Core\PaymentError;
use SmashPig\PaymentData\ErrorCode;
use SmashPig\PaymentData\FinalStatus;
use SmashPig\PaymentProviders\Responses\PaymentProviderResponse;
use UnexpectedValueException;

abstract class DlocalPaymentResponseFactory {

	abstract protected static function createBasicResponse(): PaymentProviderResponse;

	abstract protected static function getStatusNormalizer(): PaymentStatusNormalizer;

	/**
	 * @param mixed $rawResponse
	 * @return PaymentProviderResponse
	 */
	public static function fromRawResponse( $rawResponse ): PaymentProviderResponse {
		$response = static::createBasicResponse();
		try {
			$response->setRawResponse( $rawResponse );
			$gatewayTxnId = $rawResponse['id'] ?? null;
			if ( $gatewayTxnId ) {
				$response->setGatewayTxnId( $gatewayTxnId );
			}

			static::setStatusDetails( $response, static::getStatusNormalizer() );
			static::decorateResponse( $response, $rawResponse );
			if ( static::isFailedTransaction( $response->getStatus() ) ) {
				static::addPaymentFailureError( $response, $rawResponse[ 'status_detail' ], $rawResponse[ 'status_code' ] );
			}
		} catch ( UnexpectedValueException $unexpectedValueException ) {
			$responseError = 'Status element missing from dlocal response.';
			Logger::debug( $responseError, $rawResponse );

			static::addPaymentFailureError( $response, $responseError );
			$response->setStatus( FinalStatus::UNKNOWN );
			$response->setSuccessful( false );
		}
		return $response;
	}

	/**
	 * @param array $error
	 * @return PaymentProviderResponse
	 */
	public static function fromErrorResponse( array $error ): PaymentProviderResponse {
		$response = static::createBasicResponse();
		self::setErrorDetails( $response, $error );
		return $response;
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
	protected static function addPaymentFailureError( PaymentProviderResponse $paymentResponse, ?string $statusDetail = 'Missing required field', ?string $statusCode = null ): void {
		$paymentResponse->addErrors(
			new PaymentError(
				ErrorMapper::$paymentStatusErrorCodes[ $statusCode ] ?? ErrorCode::MISSING_REQUIRED_DATA,
				$statusDetail,
				LogLevel::ERROR
			)
		);
	}

	/**
	 * @param PaymentProviderResponse $paymentResponse
	 * @param array $error
	 * @return void
	 */
	protected static function setErrorDetails( PaymentProviderResponse $paymentResponse, array $error ): void {
		$code = $error['code'] ?? null;
		$errorCode = ErrorMapper::$errorCodes[ $code ] ?? null;
		$message = $error['message'] ?? "Server error";

		if ( !$errorCode ) {
			Logger::debug( 'Unable to map error code' );
			$errorCode = ErrorCode::UNEXPECTED_VALUE;
		}
		$paymentResponse->addErrors( new PaymentError( $errorCode, $message, LogLevel::ERROR ) );
		$paymentResponse->setRawResponse( $error );
		$paymentResponse->setSuccessful( false );
		$paymentResponse->setStatus( FinalStatus::FAILED );
	}

	/**
	 * @param PaymentProviderResponse $paymentResponse
	 * @param PaymentStatusNormalizer $statusMapper
	 * @throws UnexpectedValueException
	 * @return void
	 */
	protected static function setStatusDetails( PaymentProviderResponse $paymentResponse, PaymentStatusNormalizer $statusMapper ): void {
		$rawResponse = $paymentResponse->getRawResponse();
		if ( !array_key_exists( 'status', $rawResponse ) ) {
			throw new UnexpectedValueException( "Missing status" );
		}
		$rawStatus = $rawResponse['status'];
		$paymentResponse->setRawStatus( $rawStatus );
		$normalizedStatus = $statusMapper->normalizeStatus( $rawStatus );
		$paymentResponse->setStatus( $normalizedStatus );
		$paymentResponse->setSuccessful( $statusMapper->isSuccessStatus( $normalizedStatus ) );
	}

	/**
	 * @param PaymentProviderResponse $paymentResponse
	 * @param array $rawResponse
	 */
	protected static function decorateResponse( PaymentProviderResponse $paymentResponse, array $rawResponse ): void {
		// Default behavior is to do nothing here, but child classes can override it.
	}
}
