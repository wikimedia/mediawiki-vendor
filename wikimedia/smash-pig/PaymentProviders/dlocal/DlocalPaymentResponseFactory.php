<?php

namespace SmashPig\PaymentProviders\dlocal;

use Psr\Log\LogLevel;
use SmashPig\Core\PaymentError;
use SmashPig\PaymentData\ErrorCode;
use SmashPig\PaymentData\FinalStatus;
use SmashPig\PaymentProviders\Responses\PaymentProviderResponse;
use UnexpectedValueException;

abstract class DlocalPaymentResponseFactory {

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
}
