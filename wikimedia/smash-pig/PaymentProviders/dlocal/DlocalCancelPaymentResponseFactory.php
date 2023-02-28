<?php
namespace SmashPig\PaymentProviders\dlocal;

use Psr\Log\LogLevel;
use SmashPig\Core\Logging\Logger;
use SmashPig\Core\PaymentError;
use SmashPig\PaymentData\ErrorCode;
use SmashPig\PaymentData\FinalStatus;
use SmashPig\PaymentProviders\Responses\CancelPaymentResponse;

class DlocalCancelPaymentResponseFactory {

	/**
	 * @param mixed $rawResponse
	 *
	 * @return \SmashPig\PaymentProviders\Responses\CancelPaymentResponse
	 */
	public static function fromRawResponse( $rawResponse ): CancelPaymentResponse {
		$cancelPaymentResponse = new CancelPaymentResponse();
		$cancelPaymentResponse->setRawResponse( $rawResponse );
		$gatewayTxnId = $rawResponse['id'] ?? null;
		if ( $gatewayTxnId ) {
			$cancelPaymentResponse->setGatewayTxnId( $gatewayTxnId );
		}
		$rawStatus = $rawResponse['status'] ?? null;
		if ( $rawStatus ) {
			self::setStatusDetails( $cancelPaymentResponse, $rawStatus );
		} else {
			$responseError = 'cancelResult element missing from dlocal cancel response.';
			$cancelPaymentResponse->addErrors(
				new PaymentError(
					ErrorCode::MISSING_REQUIRED_DATA,
					$responseError,
					LogLevel::ERROR
				)
			);
			$cancelPaymentResponse->setStatus( FinalStatus::UNKNOWN );
			$cancelPaymentResponse->setSuccessful( false );
			Logger::debug( $responseError, $rawResponse );
		}
		return $cancelPaymentResponse;
	}

	/**
	 * @param CancelPaymentResponse $cancelPaymentResponse
	 * @param string|null $rawStatus
	 * @return void
	 */
	protected static function setStatusDetails( CancelPaymentResponse $cancelPaymentResponse, ?string $rawStatus ): void {
		$cancelPaymentResponse->setRawStatus( $rawStatus );
		$cancelPaymentStatusNormalizer = new CancelPaymentStatusNormalizer();
		$normalizedStatus = $cancelPaymentStatusNormalizer->normalizeStatus( $rawStatus );
		$cancelPaymentResponse->setStatus( $normalizedStatus );
		$isSuccessfulStatus = $cancelPaymentStatusNormalizer->isSuccessStatus( $rawStatus );
		$cancelPaymentResponse->setSuccessful( $isSuccessfulStatus );
	}
}
