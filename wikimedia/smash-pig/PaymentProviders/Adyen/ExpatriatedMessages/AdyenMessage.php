<?php namespace SmashPig\PaymentProviders\Adyen\ExpatriatedMessages;

use SmashPig\Core\Listeners\ListenerDataException;
use SmashPig\Core\Logging\Logger;
use SmashPig\Core\Messages\ListenerMessage;
use SmashPig\PaymentProviders\Adyen\WSDL\NotificationRequestItem;

abstract class AdyenMessage extends ListenerMessage {

	/** @var string If this message is a notification for a modification request this will by the pspReference
	 * that was originally assigned to the authorization. For a payment it will be blank.
	 */
	public $parentPspReference;

	/** @var string The reference Adyen assigned to the payment or modification order */
	public $pspReference;

	/** @var string The original reference string SmashPig provided to Adyen */
	public $merchantReference;

	/** @var string The account the payment or modification was processed under */
	public $merchantAccountCode;

	/** @var string ISO currency for @see $amount */
	public $currency;

	/** @var float Decimalized amount of @see $currency */
	public $amount;

	/** @var int UTC unix timestamp of the event */
	public $eventDate;

	/** @var bool True if the event was successfully processed -- if this is a response to a request it does not
	 * necessarily mean that the request itself was successful. IE: refund request received but not yet accepted.
	 */
	public $success;

	/** @var string|null Reason for event */
	public $reason;

	/**
	 * Creates an appropriate derived AdyenMessage instance from the object received
	 * during the SOAP transaction.
	 *
	 * The magic here is looking at the eventCode field, normalizing it, and then
	 * loading the class if it exists.
	 *
	 * @param \SmashPig\PaymentProviders\Adyen\WSDL\NotificationRequestItem $obj
	 */
	public static function getInstanceFromWSDL( NotificationRequestItem $msgObj ) {
		// Adyen events come in as UPPER_CASE_UNDERSCORE_DELIMITED, we turn this
		// into UpperCaseUnderscoreDelimited
		$className = implode( '', array_map( 'ucwords', explode( '_', strtolower( $msgObj->eventCode ) ) ) );
		$className = 'SmashPig\\PaymentProviders\\Adyen\\ExpatriatedMessages\\' . $className;

		if ( class_exists( $className ) ) {
			Logger::debug( "Attempting construction of '$className'" );
			$obj = new $className();
		} else {
			Logger::debug( "Class not found '$className'" );
			return false;
		}

		if ( $obj instanceof AdyenMessage ) {
			$obj->constructFromWSDL( $msgObj );
		} else {
			throw new ListenerDataException(
				"Instantiated object '{$className}' does not inherit from AdyenMessage'!"
			);
		}

		return $obj;
	}

	/**
	 * Creates an appropriate derived AdyenMessage instance from the object received
	 *  during the JSON transaction.
	 *
	 *  The magic here is looking at the eventCode field, normalizing it, and then
	 *  loading the class if it exists.
	 *
	 * @param array $notification
	 * @return false|AdyenMessage
	 * @throws ListenerDataException
	 */
	public static function getInstanceFromJSON( array $notification ) {
		// Adyen events come in as UPPER_CASE_UNDERSCORE_DELIMITED, we turn this
		// into UpperCaseUnderscoreDelimited
		$className = implode( '', array_map( 'ucwords', explode( '_', strtolower( $notification['eventCode'] ) ) ) );
		$className = 'SmashPig\\PaymentProviders\\Adyen\\ExpatriatedMessages\\' . $className;

		if ( class_exists( $className ) ) {
			Logger::debug( "Attempting construction of '$className'" );
			$obj = new $className();
		} else {
			Logger::debug( "Class not found '$className'" );
			return false;
		}

		if ( $obj instanceof AdyenMessage ) {
			$obj->constructFromJSON( $notification );
		} else {
			throw new ListenerDataException(
				"Instantiated object '$className' does not inherit from AdyenMessage'!"
			);
		}

		return $obj;
	}

	/**
	 * Called by the getInstanceFromWSDL function to continue message type specific construction
	 * after generic construction has been completed.
	 *
	 * @param NotificationRequestItem $msgObj Received and processed object
	 */
	protected function constructFromWSDL( NotificationRequestItem $msgObj ) {
		if ( $msgObj->amount ) {
			$this->currency = $msgObj->amount->currency;
			$this->amount = $msgObj->amount->value / 100;	// TODO: Make this CLDR aware
		}
		$this->eventDate = $msgObj->eventDate;
		$this->merchantAccountCode = $msgObj->merchantAccountCode;
		$this->merchantReference = $msgObj->merchantReference;
		$this->parentPspReference = $msgObj->originalReference;
		$this->pspReference = $msgObj->pspReference;
		$this->success = (bool)$msgObj->success;
		$this->reason = $msgObj->reason;
	}

	/**
	 * Called by the getInstanceFromJSON function to continue message type specific construction
	 *  after generic construction has been completed.
	 *
	 * @param array $notification
	 * @return void
	 */
	protected function constructFromJSON( array $notification ) {
		if ( !empty( $notification['amount'] ) ) {
			$this->currency = $notification['amount']['currency'];
			$this->amount = $notification['amount']['value'] / 100;	// TODO: Make this CLDR aware
		}
		$this->eventDate = $notification['eventDate'];
		$this->merchantAccountCode = $notification['merchantAccountCode'];
		$this->merchantReference = $notification['merchantReference'];
		$this->parentPspReference = $notification['originalReference'] ?? null;
		$this->pspReference = $notification['pspReference'];
		$this->success = (bool)$notification['success'];
		$this->reason = $notification['reason'];
	}

	/**
	 * Determine if the message is complete, well formed, and able to be
	 * processed. Returning true will continue processing of this message.
	 * Returning false will halt processing of the message but will not be
	 * treated as an error. Throw an exception if a critical error has
	 * occurred.
	 *
	 * @return bool True if the message was complete and can be processed
	 */
	public function validate(): bool {
		// Not sure if there's any validation we can do that hasn't already been done
		// by the WSDL processor.

		return true;
	}

	/**
	 * Returns the gateway-side ID we record for this transaction. In the
	 * case of a card payment where we get different IDs for the auth and
	 * capture, we record the ID of the authorization.
	 *
	 * @return string
	 */
	public function getGatewayTxnId() {
		return $this->pspReference;
	}
}
