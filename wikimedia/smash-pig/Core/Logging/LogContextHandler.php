<?php

namespace SmashPig\Core\Logging;

use SmashPig\Core\Logging\LogStreams\ILogStream;
use SmashPig\Core\ProviderConfiguration;

class LogContextHandler {
	/** @var [LogEvent[]] Stack of LogEvent arrays holding all log lines for a context */
	protected $contextData = [ [] ];

	/** @var string[] Stack of strings holding context names */
	protected $contextNames;

	/** @var ILogStream[] */
	protected $logStreams;

	/** @var int The log level must be greater than this to be processed. */
	protected $threshold = LOG_DEBUG;

	public function __construct( $rootName, $logStreams, $threshold ) {
		$this->contextNames = [ $rootName ];
		$this->contextString = self::createQualifiedContextName( $this->contextNames );
		$this->threshold = $threshold;

		$this->logStreams = $logStreams;
		foreach ( $this->logStreams as $stream ) {
			$stream->registerContextHandler( $this );
			$stream->enterContext( $this->contextNames );
		}
	}

	public function __destruct() {
		while ( count( $this->contextNames ) > 1 ) {
			$this->leaveContext();
		}

		// This acts as an implicit leaveContext() because it wont allow us to leave
		// the final context.
		foreach ( $this->logStreams as $stream ) {
			$stream->shutdown();
		}
	}

	/**
	 * @param ILogStream $logStream
	 */
	public function addLogStream( ILogStream $logStream ) {
		$this->logStreams[] = $logStream;
		$logStream->enterContext( $this->contextNames );
	}

	public function removeLogStreamByType( string $className ) {
		$newLogStreams = [];
		foreach ( $this->logStreams as $existingStream ) {
			if ( $existingStream instanceof $className ) {
				$existingStream->shutdown();
			} else {
				$newLogStreams[] = $existingStream;
			}
		}
		$this->logStreams = $newLogStreams;
	}

	/**
	 * Enters a new context with the current context as its parent.
	 *
	 * @param string $name Child context name
	 */
	public function enterContext( $name ) {
		if ( $name !== ProviderConfiguration::NO_PROVIDER ) {
			Logger::debug( "Entering logging context '{$name}'." );
		}

		array_unshift( $this->contextNames, $name );

		foreach ( $this->logStreams as $stream ) {
			$stream->enterContext( $this->contextNames );
		}
	}

	/**
	 * Renames the current logging context. Effects the log prefix used for all
	 * events under this context. May have adverse effects on logstreams that log
	 * in real time (IE: Syslog) because they will have logged items under the old
	 * context name.
	 *
	 * @param string $newName New name for the current context
	 * @param bool $addLogEntry If false will not create a log line stating the name change
	 *
	 * @return string The old name of this context
	 */
	public function renameContext( $newName, $addLogEntry = true ) {
		$old = $this->contextNames[ 0 ];

		if ( $addLogEntry ) {
			Logger::info( "Renaming logging context '{$old}' to '{$newName}'." );
		}

		$this->contextNames[ 0 ] = $newName;

		foreach ( $this->logStreams as $stream ) {
			$stream->renameContext( $this->contextNames, $old );
		}

		return $old;
	}

	/**
	 * Adds an event to the current context stack
	 *
	 * @param LogEvent $event Event to add
	 */
	public function addEventToContext( LogEvent $event ) {
		if ( $event->level > $this->threshold ) {
			return;
		}
		$this->contextData[ 0 ][ ] = $event;
		foreach ( $this->logStreams as $stream ) {
			$stream->processEvent( $event );
		}
	}

	/**
	 * Leaves the current context for the parent context. You may not leave the root
	 * context.
	 *
	 * Side effects include removing all stored log lines for this context. Before this
	 * happens all LogStreams have the opportunity to do last chance processing.
	 *
	 * @return string|bool The current context name, or false if this is the root context
	 */
	public function leaveContext() {
		if ( count( $this->contextNames ) > 1 ) {
			foreach ( $this->logStreams as $stream ) {
				$stream->leaveContext( $this->contextNames );
			}

			$old = array_shift( $this->contextNames );
			array_shift( $this->contextData );
		} else {
			$old = false;
		}

		return $old;
	}

	/**
	 * Obtains all log tuples (which consist of an array with keys message, data, and exception)
	 * for the given context level.
	 *
	 * @param int $n From 0 to the number of contexts - 1. 0 being the current context.
	 *
	 * @return array[{message data, exception}]
	 */
	public function getContextEntries( $n ) {
		if ( isset( $this->contextData[ $n ] ) ) {
			return $this->contextData[ $n ];
		} else {
			return [];
		}
	}

	/**
	 * Creates the fully qualified context name from the current stack. Individual nodes
	 * are separated by '::'.
	 *
	 * @param string[] $contextStack Current stack of context names
	 *
	 * @return string The fully qualified context name.
	 */
	public static function createQualifiedContextName( $contextStack ) {
		return implode( '::', array_reverse( $contextStack ) );
	}
}
