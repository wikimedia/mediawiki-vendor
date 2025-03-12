<?php
namespace SmashPig\PaymentProviders\Gravy\ExpatriatedMessages;

use SmashPig\Core\Listeners\ListenerDataException;
use SmashPig\Core\Logging\Logger;
use SmashPig\Core\Messages\ListenerMessage;

abstract class GravyMessage extends ListenerMessage {

	private string $date = '';

	public function validate(): bool {
		return true;
	}

	/**
	 * Creates an appropriate derived GravyMessage instance from the normalized notification message
	 *
	 *  The method uses the normalized "message_type" property to locate the appropriate message class.
	 *
	 * @param array $notification
	 * @return GravyMessage
	 * @throws ListenerDataException
	 */
	public static function getInstanceFromNormalizedNotification( array $notification ): GravyMessage {
		$messageClassName = $notification['message_type'];
		$className = 'SmashPig\\PaymentProviders\\Gravy\\ExpatriatedMessages\\' . $messageClassName;

		if ( class_exists( $className ) ) {
			Logger::debug( "Attempting construction of '$className'" );
			$obj = new $className();
		} else {
			throw new ListenerDataException(
				"Gravy Message Class not found '$className'"
			);
		}

		if ( $obj instanceof GravyMessage ) {
			$obj->init( $notification );
		} else {
			throw new ListenerDataException(
				"Instantiated object '$className' does not inherit from GravyMessage!"
			);
		}

		return $obj;
	}

	public function getMessageDate(): string {
		return $this->date;
	}

	public function setMessageDate( string $date ): void {
		$this->date = $date;
	}

	abstract public function init( array $notification );

	/**
	 * Returns name of Action class for the message
	 *
	 * @return string
	 */
	abstract public function getAction(): ?string;

	abstract public function getDestinationQueue(): ?string;
}
