<?php namespace SmashPig\Core\Logging\LogStreams;

use SmashPig\Core\Logging\LogContextHandler;
use SmashPig\Core\Logging\LogEvent;

class SyslogLogStream implements ILogStream {

	protected $rootContext = false;
	protected $additionalContext = '';

	/** @var int Facility code to log under -- from logging/syslog/facility */
	protected $facility;

	/** @var int Syslog options -- from logging/syslog/options */
	protected $options;

	public function __construct( $facility = LOG_LOCAL0, $options = LOG_NDELAY ) {
		$this->facility = $facility;
		$this->options = $options;
	}

	/**
	 * Function called at startup/initialization of the log streamer so that
	 * it has access to the current context beyond context names.
	 *
	 * @param LogContextHandler $ch Context handler object
	 */
	public function registerContextHandler( LogContextHandler $ch ) {
	}

	/**
	 * Process a new event into the log stream.
	 *
	 * @param LogEvent $event Event to process
	 */
	public function processEvent( LogEvent $event ) {
		$expStr = implode( ' !', $event->getExceptionBlob() );
		if ( $event->tag ) {
			$str = "{$this->additionalContext} | ({$event->tag}) {$event->message} | {$event->data} | {$expStr}";
		} else {
			$str = "{$this->additionalContext} | {$event->message} | {$event->data} | {$expStr}";
		}

		openlog( $this->rootContext[0], $this->options, $this->facility );
		syslog( $event->level, $str );
		closelog();
	}

	/**
	 * Notification callback that the log context has added a new child
	 *
	 * @param string[] $contextNames Stack of context names. $contextName[0] is
	 *                               the name of the current context
	 */
	public function enterContext( $contextNames ) {
		$this->rootContext = array_slice( $contextNames, -1 );
		$this->additionalContext = LogContextHandler::createQualifiedContextName(
			array_slice( $contextNames, 0, -1 )
		);
	}

	/**
	 * Notification callback that the log context has changed its name
	 *
	 * @param string[] $contextNames Stack of context names. $contextName[0] is
	 *                               the new name of the current context
	 * @param string $oldTopName The old name of the current context
	 */
	public function renameContext( $contextNames, $oldTopName ) {
		$this->rootContext = array_slice( $contextNames, -1 );
		$this->additionalContext = LogContextHandler::createQualifiedContextName(
			array_slice( $contextNames, 0, -1 )
		);
	}

	/**
	 * Notification callback that the log context is switching into the parent
	 * context. enterContext() will not be called.
	 *
	 * @param string[] $contextNames Stack of context names. $contextName[0] is
	 *                               the new name of the current context
	 */
	public function leaveContext( $contextNames ) {
		// We assume here that the root context will not have changed!
		$this->additionalContext = LogContextHandler::createQualifiedContextName(
			array_slice( $contextNames, 1, -1 )
		);
	}

	/**
	 * Notification callback that the logging infrastructure is shutting down
	 */
	public function shutdown() {
	}
}
