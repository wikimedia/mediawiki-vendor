<?php

namespace SmashPig\Core\Logging;

/**
 * Instantiable shadow of the main Logger class; except that this variant
 * allows the concept of 'tagging' a log message. This is really just
 * a convenience class for accessing this underlying feature of the log
 * system.
 *
 * @package SmashPig\Core\Logging
 */
class TaggedLogger {

	protected $tag = null;

	/** @var LogContextHandler */
	protected $context = null;

	public function __construct( $tag = null ) {
		$this->tag = $tag;
		$this->context = Logger::getContext();
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
	public function alert( $msg, $data = null, $ex = null ) {
		$this->context->addEventToContext( new LogEvent( LOG_ALERT, $msg, $this->tag, $data, $ex ) );
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
	public function error( $msg, $data = null, $ex = null ) {
		$this->context->addEventToContext( new LogEvent( LOG_ERR, $msg, $this->tag, $data, $ex ) );
	}

	/**
	 * Log a warning message, NOT AN ERROR, but indication that an error may occur if action
	 * is not taken, e.g. file system 85% full; db lag > 5s; APC/MemCache unavailable; etc
	 *
	 * @param string $msg Message string to log
	 * @param null|object $data Serializable data object relevant to the event, if any
	 * @param null|\Exception $ex Exception object relevant to the event, if any
	 */
	public function warning( $msg, $data = null, $ex = null ) {
		$this->context->addEventToContext( new LogEvent( LOG_WARNING, $msg, $this->tag, $data, $ex ) );
	}

	/**
	 * Log an event that is unusual but IS NOT an error condition - might be summarized in an
	 * email to developers or admins to spot potential problems - no immediate action required.
	 *
	 * @param string $msg Message string to log
	 * @param null|object $data Serializable data object relevant to the event, if any
	 * @param null|\Exception $ex Exception object relevant to the event, if any
	 */
	public function notice( $msg, $data = null, $ex = null ) {
		$this->context->addEventToContext( new LogEvent( LOG_NOTICE, $msg, $this->tag, $data, $ex ) );
	}

	/**
	 * Log information in the course of normal operational - may be harvested for reporting,
	 * measuring throughput, etc. - no action required.
	 *
	 * @param string $msg Message string to log
	 * @param null|object $data Serializable data object relevant to the event, if any
	 * @param null|\Exception $ex Exception object relevant to the event, if any
	 */
	public function info( $msg, $data = null, $ex = null ) {
		$this->context->addEventToContext( new LogEvent( LOG_INFO, $msg, $this->tag, $data, $ex ) );
	}

	/**
	 * Log information useful to developers for debugging the application; not useful
	 * during normal operation.
	 *
	 * @param string $msg Message string to log
	 * @param null|object $data Serializable data object relevant to the event, if any
	 * @param null|\Exception $ex Exception object relevant to the event, if any
	 */
	public function debug( $msg, $data = null, $ex = null ) {
		$this->context->addEventToContext( new LogEvent( LOG_DEBUG, $msg, $this->tag, $data, $ex ) );
	}
}
