<?php

namespace SmashPig\PaymentProviders\Adyen\Mapper;

use Psr\Log\LogLevel;
use SmashPig\Core\Logging\Logger;
use SmashPig\Core\PaymentError;
use SmashPig\Core\ValidationError;
use SmashPig\PaymentData\ErrorCode;
use SmashPig\PaymentProviders\Adyen\ValidationErrorMapper;
use SmashPig\PaymentProviders\Responses\PaymentProviderExtendedResponse;
use SmashPig\PaymentProviders\Responses\PaymentProviderResponse;

abstract class ResponseMapper {

	abstract protected function mapIDs( PaymentProviderResponse $response, array $rawResponse ): void;

	/**
	 * Maps a couple of common properties of Adyen Checkout API responses to our
	 * standardized PaymentProviderResponse.
	 * Their pspReference is mapped to our GatewayTxnId and their refusalReason
	 * is mapped to a PaymentError with a normalized ErrorCode
	 * TODO: some refusalReasons should get ValidationError not PaymentError
	 *
	 * @param PaymentProviderResponse $response
	 * @param array|bool|null $rawResponse
	 */
	public function mapGatewayTxnIdAndErrors(
		PaymentProviderResponse $response,
		array|null|bool $rawResponse
	): void {
		if ( !is_array( $rawResponse ) ) {
			$responseError = 'Adyen response was null or invalid JSON.';
			$response->addErrors(
				new PaymentError(
					ErrorCode::NO_RESPONSE,
					$responseError,
					LogLevel::ERROR
				)
			);
			Logger::debug( $responseError, $rawResponse );
		} else {
			$this->mapIDs( $response, $rawResponse );
			if ( !empty( $rawResponse['errorCode'] ) ) {
				$badField = ValidationErrorMapper::getValidationErrorField( $rawResponse['errorCode'] );
				if ( $badField !== null ) {
					$response->addValidationError( new ValidationError( $badField ) );
				}
			}
			// Map refusal reason to PaymentError
			if ( !empty( $rawResponse['refusalReason'] ) ) {
				if ( $this->canRetryRefusalReason( $rawResponse['refusalReason'] ) ) {
					$errorCode = ErrorCode::DECLINED;
				} else {
					$errorCode = ErrorCode::DECLINED_DO_NOT_RETRY;
				}
				$response->addErrors(
					new PaymentError(
						$errorCode,
						$rawResponse['refusalReason'],
						LogLevel::INFO
					)
				);
			}
		}
	}

	/**
	 * Documented at
	 * https://docs.adyen.com/development-resources/refusal-reasons
	 *
	 * @param string $refusalReason
	 * @return bool
	 */
	protected function canRetryRefusalReason( $refusalReason ): bool {
		// They may prefix the refusal reason with a numeric code
		$trimmedReason = preg_replace( '/^[0-9:]+ /', '', $refusalReason );
		$noRetryReasons = [
			'Acquirer Fraud',
			'Blocked Card',
			'FRAUD',
			'FRAUD-CANCELLED',
			'Invalid Amount',
			'Invalid Card Number',
			'Invalid Pin',
			'No Contract Found',
			'Pin validation not possible',
			'Referral',
			'Restricted Card',
			'Revocation Of Auth',
			'Issuer Suspected Fraud',
		];
		if ( in_array( $trimmedReason, $noRetryReasons ) ) {
			return false;
		}
		return true;
	}

	/**
	 * 'Modification' requests (e.g. approve, refund) have a paymentPspReference
	 * that refers to the authorization. The authorization ID is the one that should
	 * be stored for later references to the payment, so we assign it to the
	 * gateway txn ID property.
	 * @param PaymentProviderResponse $response
	 * @param string|null $paymentPspReference
	 * @return void
	 */
	protected function mapPaymentPspReference(
		PaymentProviderResponse $response, ?string $paymentPspReference
	): void {
		if ( $paymentPspReference ) {
			$response->setGatewayTxnId( $paymentPspReference );
			if ( $response instanceof PaymentProviderExtendedResponse ) {
				$response->setAuthID( $paymentPspReference );
				$response->setBackendProcessorTransactionId( $paymentPspReference );
			}
		}
	}
}
