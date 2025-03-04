<?php namespace SmashPig\Core\Logging;

use SmashPig\Core\ProviderConfiguration;
use SmashPig\Core\SmashPigException;

class Logger {

	/** @var LogContextHandler */
	protected static $context = null;

	/**
	 * Initialize the logging framework.
	 *
	 * Will add all LogStreams registered under logging/enabled-log-streams. These
	 * handlers must have class instantiation entries under logging/log-streams/<name>.
	 *
	 * @param string $name Root context name
	 * @param int $threshold Minimum log level to record into the context
	 * @param ProviderConfiguration $config Configuration object to use
	 * @param string $prefix Base prefix for logger
	 */
	public static function init( $name, $threshold, ProviderConfiguration $config, $prefix ) {
		if ( self::$context ) {
			self::$context = null; // should fire __destruct code
		}

		// Init all the log streams
		$streamObjs = [];
		try {
			$streams = $config->val( 'logging/enabled-log-streams' );

			foreach ( $streams as $streamName ) {
				$streamObjs[] = $config->object( "logging/log-streams/$streamName", false );
			}

		} catch ( \Exception $ex ) {
			trigger_error(
				"Exception while creating default log streams: {$ex->getMessage()} at {$ex->getTraceAsString()}",
				E_USER_ERROR
			);
			die();
		}

		self::$context = new LogContextHandler( $name, $streamObjs, $threshold );
		self::$context->enterContext( $prefix );
	}

	/* === HELPER METHODS === */

	/**
	 * Creates and returns a logger that is capable of adding tags to log lines for the
	 * purposes of differentiating log line output/sources beyond the context.
	 *
	 * @param string $tag Tag to apply
	 *
	 * @return TaggedLogger
	 */
	public static function getTaggedLogger( $tag ) {
		// We actually have this function so the TaggedLogger class is discoverable
		return new TaggedLogger( $tag );
	}

	public static function setPrefix( $prefix ) {
		self::$context->leaveContext();
		self::$context->enterContext( $prefix );
	}

	public static function removeLogStreamByType( string $className ) {
		self::$context->removeLogStreamByType( $className );
	}

	/* --- CONTEXT HELPER METHODS --- */

	/**
	 * Obtain the logging context. Only one context will be present in an
	 * instantiation of Logger (which implies only one per process.)
	 *
	 * @throws SmashPigException if logger has not been initialized
	 * @return LogContextHandler
	 */
	public static function getContext() {
		if ( self::$context === null ) {
			throw new SmashPigException( "No context available. Logger not initialized?" );
		}
		return self::$context;
	}

	/* === EVENT HANDLING === */

	/**
	 * Log an immediate/critical failure. Will be immediately forwarded to the designated
	 * error contact. Use this for things like database failures, top of PHP error stack
	 * exceptions, and non recoverable errors like being unable to requeue a message.
	 *
	 * @param string $msg Message string to log
	 * @param null|object $data Serializable data object relevant to the event, if any
	 * @param null|\Exception $ex Exception object relevant to the event, if any
	 */
	public static function alert( $msg, $data = null, $ex = null ) {
		self::$context->addEventToContext( new LogEvent( LOG_ALERT, $msg, null, $data, $ex ) );
	}

	/**
	 * Log a non-urgent failure. Will be forwarded onto the designated error contact, but
	 * may be digested/filtered in some way. Use this for malformed data, and recoverable
	 * exceptions (ie: a queued message could not be processed but could be requeued.)
	 *
	 * @param string $msg Message string to log
	 * @param null|object $data Serializable data object relevant to the event, if any
	 * @param null|\Exception $ex Exception object relevant to the event, if any
	 */
	public static function error( $msg, $data = null, $ex = null ) {
		self::$context->addEventToContext( new LogEvent( LOG_ERR, $msg, null, $data, $ex ) );
	}

	/**
	 * Log a warning message, NOT AN ERROR, but indication that an error may occur if action
	 * is not taken, e.g. file system 85% full; db lag > 5s; APC/MemCache unavailable; etc
	 *
	 * @param string $msg Message string to log
	 * @param null|object $data Serializable data object relevant to the event, if any
	 * @param null|\Exception $ex Exception object relevant to the event, if any
	 */
	public static function warning( $msg, $data = null, $ex = null ) {
		self::$context->addEventToContext( new LogEvent( LOG_WARNING, $msg, null, $data, $ex ) );
	}

	/**
	 * Log an event that is unusual but IS NOT an error condition - might be summarized in an
	 * email to developers or admins to spot potential problems - no immediate action required.
	 *
	 * @param string $msg Message string to log
	 * @param null|object $data Serializable data object relevant to the event, if any
	 * @param null|\Exception $ex Exception object relevant to the event, if any
	 */
	public static function notice( $msg, $data = null, $ex = null ) {
		self::$context->addEventToContext( new LogEvent( LOG_NOTICE, $msg, null, $data, $ex ) );
	}

	/**
	 * Log information in the course of normal operational - may be harvested for reporting,
	 * measuring throughput, etc. - no action required.
	 *
	 * @param string $msg Message string to log
	 * @param null|object $data Serializable data object relevant to the event, if any
	 * @param null|\Exception $ex Exception object relevant to the event, if any
	 */
	public static function info( $msg, $data = null, $ex = null ) {
		self::$context->addEventToContext( new LogEvent( LOG_INFO, $msg, null, $data, $ex ) );
	}

	/**
	 * Log information useful to developers for debugging the application; not useful
	 * during normal operation.
	 *
	 * @param string $msg Message string to log
	 * @param null|object $data Serializable data object relevant to the event, if any
	 * @param null|\Exception $ex Exception object relevant to the event, if any
	 */
	public static function debug( $msg, $data = null, $ex = null ) {
		self::$context->addEventToContext( new LogEvent( LOG_DEBUG, $msg, null, $data, $ex ) );
	}
}
