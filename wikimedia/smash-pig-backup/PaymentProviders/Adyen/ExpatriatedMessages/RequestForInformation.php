<?php namespace SmashPig\PaymentProviders\Adyen\ExpatriatedMessages;

use SmashPig\PaymentProviders\Adyen\Actions\PaymentCaptureAction;

/**
 * A REQUEST_FOR_INFORMATION message is sent as a preliminary stage
 * for a chargeback process. In theory this means that the account
 * holder needs to defend why the chargeback should not be upheld.
 *
 * @package SmashPig\PaymentProviders\Adyen\ExpatriatedMessages
 */
class RequestForInformation extends AdyenMessage {

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
		$action = new PaymentCaptureAction();
		$result = $action->execute( $this );

		if ( $result === true ) {
			return parent::runActionChain();
		} else {
			return false;
		}
	}
}
