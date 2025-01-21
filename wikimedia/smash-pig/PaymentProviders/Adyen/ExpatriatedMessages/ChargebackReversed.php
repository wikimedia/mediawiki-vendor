<?php namespace SmashPig\PaymentProviders\Adyen\ExpatriatedMessages;

use SmashPig\PaymentProviders\Adyen\Actions\ChargebackReversedAction;

/**
 * A CHARGEBACK_REVERSED message is sent when the chargeback has been
 * canceled somehow.
 *
 * @package SmashPig\PaymentProviders\Adyen\ExpatriatedMessages
 */
class ChargebackReversed extends AdyenMessage {

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
		$action = new ChargebackReversedAction();
		$result = $action->execute( $this );

		if ( $result === true ) {
			return parent::runActionChain();
		} else {
			return false;
		}
	}
}
