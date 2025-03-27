<?php namespace SmashPig\Core\Logging;

use SmashPig\Core\DataStores\DataSerializationException;
use SmashPig\Core\DataStores\JsonSerializableObject;

class LogEvent {
	/** @var int Event priority, e.g. LOG_DEBUG */
	public $level;

	/** @var string Identifying subclass of this event */
	public $tag;

	/** @var string ISO time string of event */
	public $datestring;

	/** @var int System time in seconds of event */
	public $timestamp;

	/** @var string Human readable message for the event */
	public $message;

	/** @var string Serialized data attached to the event */
	public $data;

	/** @var \Throwable exception or error object thrown coincident to the event */
	public $exception;

	/**
	 * Construct a new log event from parameters.
	 *
	 * @param int $level The RFC log level, e.g. LOG_ALERT
	 * @param string $message Human readable string about the event. Do not include sensitive information here
	 * @param string|null $tag Optional descriptive tag, e.g. RawData
	 * @param mixed $data Optional data object (should be serializable); may include sensitive information
	 * @param \Throwable|null $exception Optional exception object related to this event
	 * @param int|string|null $timestamp Optional Unix timestamp, or date string of event. If not given this assumes now
	 *
	 * @todo uncomment Throwable type hint when PHP 5.6 goes away
	 */
	public function __construct(
		$level, $message, $tag = null, $data = null, /*\Throwable*/ $exception = null, $timestamp = null
	) {
		if ( !is_int( $level ) || ( $level > LOG_DEBUG ) || ( $level < LOG_ALERT ) ) {
			$this->level = LOG_ERR;
		} else {
			$this->level = $level;
		}

		$this->message = $message;
		$this->tag = $tag;
		$this->exception = $exception;

		if ( $data !== null ) {
			$jdata = false;
			if ( $data instanceof JsonSerializableObject ) {
				try {
					$jdata = $data->toJson();
				} catch ( DataSerializationException $ex ) {
				}
			} else {
				$jdata = json_encode( $data );
			}

			if ( $jdata ) {
				$this->data = $jdata;
			} else {
				$this->data = '"!!NON SERIALIZABLE DATA!!"';
			}
		} else {
			$this->data = null;
		}

		if ( $exception ) {
			if ( $this->data === null ) {
				$this->data = $exception->getTraceAsString();
			} else {
				$this->data .= $exception->getTraceAsString();
			}
		}
		if ( !$timestamp ) {
			$this->timestamp = time();
			$this->datestring = date( 'c' );
		} elseif ( is_int( $timestamp ) ) {
			$this->timestamp = $timestamp;
			$this->datestring = date( 'c', $timestamp );
		} elseif ( is_string( $timestamp ) ) {
			$this->datestring = $timestamp;
			$this->timestamp = strtotime( $timestamp );
		}
	}

	/**
	 * Format the exception chain to be human readable
	 *
	 * @return array Each element is the type, location, line, and message
	 * of an exception in the causal chain, with the root cause listed first.
	 * We do not include the stack trace here, as it could include sensitive
	 * data.
	 */
	public function getExceptionBlob() {
		$cex = $this->exception;
		if ( !$cex ) {
			return [];
		}

		// Get the caused by header
		$descStr = [];
		do {
			$descStr[] = get_class( $cex ) . "@{$cex->getFile()}:{$cex->getLine()} ({$cex->getMessage()})";
			$cex = $cex->getPrevious();
			if ( $cex ) {
				$descStr[] = ' -> ';
			}
		} while ( $cex );
		$descStr = array_reverse( $descStr );

		return $descStr;
	}
}
