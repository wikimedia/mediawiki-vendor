<?php namespace SmashPig\Core\Logging\LogStreams;

use SmashPig\Core\Logging\LogContextHandler;
use SmashPig\Core\Logging\LogEvent;
use SmashPig\Core\SmashPigException;

class TaggedFileLogStream implements ILogStream {
	/** @var string Stores the current context string in a file system safe manner */
	protected $contextName = '';

	protected $tags = [];

	protected $directory = '';

	public function __construct( $directory, $tags ) {
		$this->directory = realpath( $directory );
		if ( !$this->directory ) {
			throw new SmashPigException( "Directory '$directory' does not exist for log stream files!" );
		} elseif ( !is_writable( $this->directory ) ) {
			throw new SmashPigException( "Directory '$directory' is not writeable!" );
		}

		if ( !is_array( $tags ) ) {
			$tags = [ $tags ];
		}
		$this->tags = $tags;
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
		if ( in_array( $event->tag, $this->tags ) ) {
			$date = strftime( '%Y%m%dT%H%M%S', $event->timestamp );
			$filename = $this->directory . '/' . $date . '.' . $this->contextName;

			$expStr = implode( '\n\t', $event->getExceptionBlob() );
			$str = "{$event->message} | {$event->data} | {$expStr}";

			file_put_contents( $filename, $str );
		}
	}

	/**
	 * Notification callback that the log context has added a new child
	 *
	 * @param string[] $contextNames Stack of context names. $contextName[0] is
	 *                               the name of the current context
	 */
	public function enterContext( $contextNames ) {
		$this->contextName = implode( '.', array_reverse( $contextNames ) );
	}

	/**
	 * Notification callback that the log context has changed its name
	 *
	 * @param string[] $contextNames Stack of context names. $contextName[0] is
	 *                               the new name of the current context
	 * @param string $oldTopName The old name of the current context
	 */
	public function renameContext( $contextNames, $oldTopName ) {
		$this->contextName = implode( '.', array_reverse( $contextNames ) );
	}

	/**
	 * Notification callback that the log context is switching into the parent
	 * context. enterContext() will not be called.
	 *
	 * @param string[] $contextNames Stack of context names. $contextName[0] is
	 *                               the new name of the current context
	 */
	public function leaveContext( $contextNames ) {
		$this->contextName = implode( '.', array_reverse( array_slice( $contextNames, 1 ) ) );
	}

	/**
	 * Notification callback that the logging infrastructure is shutting down
	 */
	public function shutdown() {
	}
}
