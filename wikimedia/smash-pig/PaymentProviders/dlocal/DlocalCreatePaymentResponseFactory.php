<?php

namespace SmashPig\PaymentProviders\dlocal;

use Psr\Log\LogLevel;
use SmashPig\Core\Logging\Logger;
use SmashPig\Core\PaymentError;
use SmashPig\PaymentData\ErrorCode;
use SmashPig\PaymentData\FinalStatus;
use SmashPig\PaymentProviders\Responses\CreatePaymentResponse;
use SmashPig\PaymentProviders\Responses\CreatePaymentResponseFactory;
use UnexpectedValueException;

class DlocalCreatePaymentResponseFactory extends CreatePaymentResponseFactory {

	/**
	 * @param mixed $rawResponse
	 * @return CreatePaymentResponse
	 */
	public static function fromRawResponse( $rawResponse ): CreatePaymentResponse {
		$createPaymentResponse = new CreatePaymentResponse();
		$createPaymentResponse->setRawResponse( $rawResponse );
		$rawStatus = $rawResponse['status'] ?? null;
		$gatewayTxnId = $rawResponse['id'] ?? null;

		if ( $gatewayTxnId ) {
			$createPaymentResponse->setGatewayTxnId( $gatewayTxnId );
		}

		self::setRedirectURL( $rawResponse, $createPaymentResponse );

		try {
			if ( !$rawStatus ) {
				throw new UnexpectedValueException( "Unknown status" );
			}
			self::setStatusDetails( $createPaymentResponse, $rawStatus );

			if ( $createPaymentResponse->getStatus() === FinalStatus::FAILED ) {
				$createPaymentResponse->addErrors(
					new PaymentError(
						ErrorMapper::$paymentStatusErrorCodes[ $rawResponse[ 'status_code' ] ] ?? ErrorCode::UNKNOWN,
					$rawResponse[ 'status_detail' ],
						LogLevel::ERROR ) );
			}

			if ( array_key_exists( 'card', $rawResponse )
				&& array_key_exists( 'card_id', $rawResponse['card'] ) ) {
				$createPaymentResponse->setRecurringPaymentToken( $rawResponse['card']['card_id'] );
			}

		} catch ( UnexpectedValueException $unexpectedValueException ) {
			Logger::debug( 'Create Payment failed', $rawResponse );

			$code = $rawResponse['code'] ?? null;
			$errorCode = ErrorMapper::$errorCodes[ $code ] ?? null;
			$message = $rawResponse['message'] ?? $unexpectedValueException->getMessage();

			if ( !$errorCode ) {
				Logger::debug( 'Unable to map error code' );
				$errorCode = ErrorCode::UNEXPECTED_VALUE;
			}
			$createPaymentResponse->addErrors( new PaymentError( $errorCode, $message, LogLevel::ERROR ) );
			$createPaymentResponse->setSuccessful( false );
			$createPaymentResponse->setStatus( FinalStatus::UNKNOWN );
		}

		return $createPaymentResponse;
	}

	/**
	 * @param array $rawResponse
	 * @param CreatePaymentResponse $createPaymentResponse
	 * @return void
	 */
	protected static function setRedirectURL( array $rawResponse, CreatePaymentResponse $createPaymentResponse ): void {
		if ( array_key_exists( 'redirect_url', $rawResponse ) ) {
			$createPaymentResponse->setRedirectUrl( $rawResponse['redirect_url'] );
		}

		if ( array_key_exists( 'three_dsecure', $rawResponse )
			&& array_key_exists( 'redirect_url', $rawResponse['three_dsecure'] ) ) {
			$createPaymentResponse->setRedirectUrl( $rawResponse['three_dsecure']['redirect_url'] );
		}
	}

	/**
	 * @param CreatePaymentResponse $createPaymentResponse
	 * @param string|null $rawStatus
	 * @return void
	 */
	protected static function setStatusDetails( CreatePaymentResponse $createPaymentResponse, ?string $rawStatus ): void {
		$createPaymentResponse->setRawStatus( $rawStatus );
		$statusMapper = new CreatePaymentStatusNormalizer();
		$normalizedStatus = $statusMapper->normalizeStatus( $rawStatus );
		$createPaymentResponse->setStatus( $normalizedStatus );
		$createPaymentResponse->setSuccessful( $statusMapper->isSuccessStatus( $normalizedStatus ) );
	}
}
