<?php namespace SmashPig\Core\Logging\LogStreams;

use SmashPig\Core\Logging\LogContextHandler;
use SmashPig\Core\Logging\LogEvent;

interface ILogStream {
	/**
	 * Process a new event into the log stream.
	 *
	 * @param LogEvent $event Event to process
	 */
	public function processEvent( LogEvent $event );

	/**
	 * Function called at startup/initialization of the log streamer so that
	 * it has access to the current context beyond context names.
	 *
	 * @param LogContextHandler $ch Context handler object
	 */
	public function registerContextHandler( LogContextHandler $ch );

	/**
	 * Notification callback that the log context has added a new child
	 *
	 * @param string[] $contextNames Stack of context names. $contextName[0] is
	 *                               the name of the current context
	 */
	public function enterContext( $contextNames );

	/**
	 * Notification callback that the log context has changed its name
	 *
	 * @param string[] $contextNames Stack of context names. $contextName[0] is
	 *                               the new name of the current context
	 * @param string $oldTopName The old name of the current context
	 */
	public function renameContext( $contextNames, $oldTopName );

	/**
	 * Notification callback that the log context is switching into the parent
	 * context. enterContext() will not be called.
	 *
	 * @param string[] $contextNames Stack of context names. $contextName[0] is
	 *                               the new name of the current context
	 */
	public function leaveContext( $contextNames );

	/**
	 * Notification callback that the logging infrastructure is shutting down
	 */
	public function shutdown();
}
