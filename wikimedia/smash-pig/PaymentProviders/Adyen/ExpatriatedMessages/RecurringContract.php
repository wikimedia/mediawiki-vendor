<?php namespace SmashPig\PaymentProviders\Adyen\ExpatriatedMessages;

use SmashPig\PaymentProviders\Adyen\Actions\RecurringContractAction;
use SmashPig\PaymentProviders\Adyen\WSDL\NotificationRequestItem;

/**
 * An Adyen RECURRING_CONTRACT message is sent from the server to SmashPig after
 * a new recurring is created. For iDEAL the message contains the recurring_payment_token
 * and the processor_contact_id
 *
 * @see RecurringContractAction
 *
 * @package SmashPig\PaymentProviders\Adyen\ExpatriatedMessages
 */
class RecurringContract extends AdyenMessage {

	/** @var string The payment method used in this transaction, eg visa, mc, ideal, ev, wallie, etc... */
	public $paymentMethod;

	/**
	 * Overloads the generic Adyen method adding fields specific to the Recurring Contract message
	 * type.
	 *
	 * @param \SmashPig\PaymentProviders\Adyen\WSDL\NotificationRequestItem $msgObj
	 */
	protected function constructFromWSDL( NotificationRequestItem $msgObj ) {
		parent::constructFromWSDL( $msgObj );

		$this->paymentMethod = $msgObj->paymentMethod;
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
		$action = new RecurringContractAction();
		$result = $action->execute( $this );

		if ( $result === true ) {
			return parent::runActionChain();
		} else {
			return false;
		}
	}

	public function getGatewayTxnId() {
		return $this->parentPspReference;
	}
}
