<?php

namespace SmashPig\PaymentProviders\Adyen\ExpatriatedMessages;

use SmashPig\PaymentProviders\Adyen\Actions\CancelRecurringAction;

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

	/**
	 * Will run all the actions that are loaded (from the 'actions' configuration
	 * node) and that are applicable to this message type. Will return true
	 * if all actions returned true. Otherwise will return false. This implicitly
	 * means that the message will be re-queued if any action fails. Therefore
	 * all actions need to be idempotent.
	 *
	 * @return bool True if all actions were successful. False otherwise.
	 */
	public function runActionChain() {
		if ( $this->isEndedAutoRescue() ) {
			return ( new CancelRecurringAction() )->execute( $this );
		}
		return true;
	}

	/**
	 * Only capture Authorisation AutoRescue message
	 *
	 * @return bool
	 */
	public function processAutoRescueCapture(): bool {
		return false;
	}
}
