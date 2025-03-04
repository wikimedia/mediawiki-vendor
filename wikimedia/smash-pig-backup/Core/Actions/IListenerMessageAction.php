<?php namespace SmashPig\Core\Actions;

use SmashPig\Core\Messages\ListenerMessage;

interface IListenerMessageAction {
	/**
	 * Take a ListenerMessage and apply any required action to it. This function
	 * is expected to return true if the action succeeded or if the action was not
	 * applicable to the message. Only return false if the action failed.
	 *
	 * @param \SmashPig\Core\Messages\ListenerMessage $msg
	 *
	 * @return bool True if action was successful
	 */
	public function execute( ListenerMessage $msg ): bool;
}
