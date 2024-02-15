<?php

namespace SmashPig\PaymentProviders\Adyen\ExpatriatedMessages;

class Autorescue extends Authorisation {

	/**
	 * Check if message is a successful auto rescue payment
	 *
	 * @return bool True if successful auto rescue
	 */
	public function isSuccessfulAutoRescue(): bool {
		if ( $this->success && empty( $this->retryNextAttemptDate ) ) {
			return true;
		}
		return false;
	}
}
