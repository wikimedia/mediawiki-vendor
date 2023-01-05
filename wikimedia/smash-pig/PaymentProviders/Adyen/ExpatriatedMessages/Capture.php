<?php namespace SmashPig\PaymentProviders\Adyen\ExpatriatedMessages;

use SmashPig\PaymentProviders\Adyen\Actions\CaptureResponseAction;
use SmashPig\PaymentProviders\Adyen\WSDL\NotificationRequestItem;

/**
 * An Adyen Capture message is sent from the server to SmashPig after
 * the request has been made to capture the payment. Receipt of this
 * message is the final indication of if a payment has been successfully
 * completed or not. E.g. Status: Success in this message indicates that
 * money will end up in your bank account.
 *
 * @see CaptureResponseAction
 *
 * @package SmashPig\PaymentProviders\Adyen\ExpatriatedMessages
 */
class Capture extends AdyenMessage {

	/**
	 * @var string the reference for this capture's original payment authorization
	 */
	public $originalReference;

	/**
	 * Overloads the generic Adyen method adding fields specific to the Capture message
	 * type.
	 *
	 * @param \SmashPig\PaymentProviders\Adyen\WSDL\NotificationRequestItem $msgObj
	 */
	protected function constructFromWSDL( NotificationRequestItem $msgObj ) {
		parent::constructFromWSDL( $msgObj );

		$this->originalReference = $msgObj->originalReference;
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
		$action = new CaptureResponseAction();
		$result = $action->execute( $this );

		if ( $result === true ) {
			return parent::runActionChain();
		} else {
			return false;
		}
	}

	/**
	 * We want to record the payment under the authorization reference, not the
	 * capture reference.
	 *
	 * @return string
	 */
	public function getGatewayTxnId() {
		return $this->originalReference;
	}
}
