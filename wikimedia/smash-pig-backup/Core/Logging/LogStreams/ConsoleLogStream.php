<?php namespace SmashPig\Core\Logging\LogStreams;

use SmashPig\Core\Logging\LogContextHandler;
use SmashPig\Core\Logging\LogEvent;

class ConsoleLogStream implements ILogStream {

	/** @var string Current fully qualified context name */
	protected $contextName;

	/** @var LogContextHandler */
	protected $context;

	protected $levelNames = [
		LOG_ALERT   => 'ALERT',
		LOG_ERR     => 'ERROR',
		LOG_WARNING => 'WARNING',
		LOG_INFO    => 'INFO',
		LOG_NOTICE  => 'NOTICE',
		LOG_DEBUG   => 'DEBUG',
	];

	public function registerContextHandler( LogContextHandler $ch ) {
		$this->context = $ch;
	}

	/**
	 * Process a new event into the log stream.
	 *
	 * @param LogEvent $event Event to process
	 */
	public function processEvent( LogEvent $event ) {
		$name = $this->levelNames[ $event->level ];

		print ( sprintf( "%s [%-7s] {%s} %s\n", $event->datestring, $name, $this->contextName, $event->message ) );

		$expStr = implode( "\n\t", $event->getExceptionBlob() );
		if ( $expStr ) {
			print ( $expStr . "\n" );
		}
	}

	/**
	 * Notification callback that the log context has added a new child
	 *
	 * @param string[] $contextNames Stack of context names. $contextName[0] is
	 *                               the name of the current context
	 */
	public function enterContext( $contextNames ) {
		$this->contextName = LogContextHandler::createQualifiedContextName( $contextNames );
	}

	/**
	 * Notification callback that the log context has changed its name
	 *
	 * @param string[] $contextNames Stack of context names. $contextName[0] is
	 *                               the new name of the current context
	 * @param string $oldTopName The old name of the current context
	 */
	public function renameContext( $contextNames, $oldTopName ) {
		$this->contextName = LogContextHandler::createQualifiedContextName( $contextNames );
	}

	/**
	 * Notification callback that the log context is switching into the parent context
	 *
	 * @param string[] $contextNames Stack of context names. $contextName[0] is
	 *                               the new name of the current context
	 */
	public function leaveContext( $contextNames ) {
	}

	/**
	 * Notification callback that the logging infrastructure is shutting down
	 */
	public function shutdown() {
	}
}
