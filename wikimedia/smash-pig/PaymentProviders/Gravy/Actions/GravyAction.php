<?php

namespace SmashPig\PaymentProviders\Gravy\Actions;

use SmashPig\Core\Actions\IListenerMessageAction;
use SmashPig\Core\Listeners\ListenerDataException;
use SmashPig\Core\Logging\Logger;

abstract class GravyAction implements IListenerMessageAction {
	 /**
	  * Creates an appropriate GravyAction instance from the Gravy Message object
	  *
	  * @param array $notification
	  * @return false|GravyAction
	  * @throws ListenerDataException
	  */
	public static function getInstanceOf( string $action ) {
		$className = 'SmashPig\\PaymentProviders\\Gravy\\Actions\\' . $action;

		if ( class_exists( $className ) ) {
			Logger::debug( "Attempting construction of '$className'" );
			$obj = new $className();
		} else {
			Logger::debug( "Gravy Action Class not found '$className'" );
			throw new ListenerDataException(
				"Gravy Action Class not found '$className'"
			);
		}

		if ( !( $obj instanceof GravyAction ) ) {
			throw new ListenerDataException(
				"Instantiated object '$className' does not inherit from GravyAction!"
			);
		}

		return $obj;
	}
}
