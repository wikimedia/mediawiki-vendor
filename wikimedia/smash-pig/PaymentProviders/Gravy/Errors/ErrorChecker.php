<?php

namespace SmashPig\PaymentProviders\Gravy\Errors;

use SmashPig\PaymentData\FinalStatus;
use SmashPig\PaymentProviders\Gravy\PaymentStatusNormalizer;

/**
 * Class ErrorChecker
 *
 * Provides methods to check for and retrieve error details from Gravy payment responses.
 * Determines the presence of errors based on specific conditions such as error response type,
 * error codes, intent outcomes, 3D Secure errors, and failed payment statuses.
 */

class ErrorChecker {

	/**
	 * Check if a Gravy payment response contains any error indicators
	 */
	public function responseHasErrors( array $response ): bool {
		return $this->hasErrorResponseType( $response ) ||
			$this->hasErrorCode( $response ) ||
			$this->hasFailedIntentOutcome( $response ) ||
			$this->has3DSecureError( $response ) ||
			$this->hasFailedPaymentStatus( $response );
	}

	/**
	 * Get error details from a Gravy payment response
	 */
	public function getResponseErrorDetails( array $response ): array {
		$errorDetails = [];

		if ( $this->hasErrorResponseType( $response ) ) {
			$errorDetails['error_type'] = ErrorType::RESPONSE_TYPE->value;
			$errorDetails['error_code'] = $response['status'] ?? 'unknown';
			return $errorDetails;
		}

		if ( $this->hasErrorCode( $response ) ) {
			$errorDetails['error_type'] = ErrorType::ERROR_CODE->value;
			$errorDetails['error_code'] = $response['error_code'];
			return $errorDetails;
		}

		if ( $this->hasFailedIntentOutcome( $response ) ) {
			$errorDetails['error_type'] = ErrorType::FAILED_INTENT->value;
			$errorDetails['error_code'] = $response['intent_outcome'];
			return $errorDetails;
		}

		if ( $this->has3DSecureError( $response ) ) {
			$errorDetails['error_type'] = ErrorType::THREE_D_SECURE->value;
			$errorDetails['error_code'] = $response['three_d_secure']['status'] ?? 'unknown_3ds_error';
			return $errorDetails;
		}

		if ( $this->hasFailedPaymentStatus( $response ) ) {
			$errorDetails['error_type'] = ErrorType::FAILED_PAYMENT->value;
			$errorDetails['error_code'] = $response['status'];
			return $errorDetails;
		}

		return [];
	}

	protected function hasErrorResponseType( array $response ): bool {
		return ( $response['type'] ?? null ) === 'error';
	}

	protected function hasErrorCode( array $response ): bool {
		return isset( $response['error_code'] );
	}

	protected function hasFailedIntentOutcome( array $response ): bool {
		return ( $response['intent_outcome'] ?? null ) === 'failed';
	}

	protected function has3DSecureError( array $response ): bool {
		return ( $response['three_d_secure']['status'] ?? null ) === 'error';
	}

	protected function hasFailedPaymentStatus( array $response ): bool {
		return isset( $response['status'] ) && ( new PaymentStatusNormalizer )->normalizeStatus( $response['status'] ) === FinalStatus::FAILED;
	}
}
