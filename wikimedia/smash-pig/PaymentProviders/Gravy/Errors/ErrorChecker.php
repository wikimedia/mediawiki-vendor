<?php

namespace SmashPig\PaymentProviders\Gravy\Errors;

use SmashPig\PaymentData\FinalStatus;
use SmashPig\PaymentProviders\Gravy\PaymentStatusNormalizer;

class ErrorChecker {

	/**
	 * Check if a Gravy payment response contains any error indicators
	 */
	public function responseHasErrors( array $response ): bool {
		if ( $this->hasErrorResponseType( $response ) ) {
			return true;
		}

		if ( $this->hasErrorCode( $response ) ) {
			return true;
		}

		if ( $this->hasFailedIntentOutcome( $response ) ) {
			return true;
		}

		if ( $this->has3DSecureError( $response ) ) {
			return true;
		}

		if ( $this->hasFailedPaymentStatus( $response ) ) {
			return true;
		}

		return false;
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
		return isset( $response['status'] ) && ( new PaymentStatusNormalizer() )->normalizeStatus( $response['status'] ) === FinalStatus::FAILED;
	}
}
