<?php namespace SmashPig\PaymentProviders\Adyen\ExpatriatedMessages;

use SmashPig\PaymentProviders\Adyen\Actions\ChargebackInitiatedAction;

/**
 * A CHARGEBACK message is sent as the final stage of the chargeback
 * process. At this point the money will have been debited from the
 * account. This is not sent if a REQUEST_FOR_INFORMATION or
 * NOTIFICATION_OF_CHARGEBACK notification has already been sent.
 *
 * @package SmashPig\PaymentProviders\Adyen\ExpatriatedMessages
 */
class Chargeback extends AdyenMessage {
	/** @var string The payment method used in this transaction, eg visa, mc, ideal, ev, wallie, etc... */
	public $paymentMethod = '';

	/** @var string The merchant reference, as order id */
	public $merchantReference = '';

	/**
	 * add payment method the Chargeback message
	 *
	 * @param array $notification
	 */
	protected function constructFromJSON( array $notification ) {
		parent::constructFromJSON( $notification );
		$this->paymentMethod = $notification['paymentMethod'] ?? '';
		$this->merchantReference = $notification['merchantReference'] ?? '';
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
		$action = new ChargebackInitiatedAction();
		$result = $action->execute( $this );

		if ( $result === true ) {
			return parent::runActionChain();
		} else {
			return false;
		}
	}
}
