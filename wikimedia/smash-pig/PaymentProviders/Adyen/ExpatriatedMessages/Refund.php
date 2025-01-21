<?php namespace SmashPig\PaymentProviders\Adyen\ExpatriatedMessages;

use SmashPig\PaymentProviders\Adyen\Actions\RefundInitiatedAction;

class Refund extends AdyenMessage {
	/** @var string The payment method used in this transaction, eg visa, mc, ideal, ev, wallie, etc... */
	public $paymentMethod = '';

	/** @var string The merchant reference, as order id */
	public $merchantReference = '';

	/**
	 * add payment method the Refund message
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
		$action = new RefundInitiatedAction();
		$result = $action->execute( $this );

		if ( $result === true ) {
			return parent::runActionChain();
		} else {
			return false;
		}
	}
}
