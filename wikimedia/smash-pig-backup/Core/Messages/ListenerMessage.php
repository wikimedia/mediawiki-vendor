<?php namespace SmashPig\Core\Messages;

use SmashPig\Core\Actions\IListenerMessageAction;
use SmashPig\Core\Context;
use SmashPig\Core\Logging\Logger;

abstract class ListenerMessage {

	/**
	 * Determine if the message is complete, well formed, and able to be
	 * processed. Returning true will continue processing of this message.
	 * Returning false will halt processing of the message but will not be
	 * treated as an error. Throw an exception if a critical error has
	 * occurred.
	 *
	 * @return bool True if the message was complete and can be processed
	 */
	abstract public function validate(): bool;

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
		$retval = true;

		// TODO: Cache this?
		$actions = Context::get()->getProviderConfiguration()->val( 'actions' );

		foreach ( $actions as $actionClassName ) {
			$action = new $actionClassName;
			if ( $action instanceof IListenerMessageAction ) {
				Logger::debug( "Running action {$actionClassName}." );
				if ( !$action->execute( $this ) ) {
					Logger::info( "Action {$actionClassName} did not execute properly, will re-queue." );
					$retval = false;
					break;
				} else {
					Logger::debug( "Action returned success." );
				}

			} else {
				Logger::error(
					"Entry under actions node '{$actionClassName}' does not implement IListenerActionMessage"
				);
			}
		}

		return $retval;
	}
}
