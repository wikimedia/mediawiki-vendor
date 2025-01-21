<?php

namespace SmashPig\Core;

/**
 * Represents an internal, payment processor, or general error.
 */
class PaymentError {
	protected $errorCode;
	protected $debugMessage;
	protected $logLevel;

	/**
	 * @param string $errorCode
	 * @param string $debugMessage
	 * @param string $logLevel one of the constants in @see Psr\Log\LogLevel
	 */
	public function __construct( $errorCode, $debugMessage, $logLevel ) {
		$this->errorCode = $errorCode;
		$this->debugMessage = $debugMessage;
		$this->logLevel = $logLevel;
	}

	/**
	 * @return string
	 */
	public function getErrorCode() {
		return $this->errorCode;
	}

	/**
	 * @return string
	 */
	public function getDebugMessage() {
		return $this->debugMessage;
	}

	/**
	 * @return string
	 */
	public function getLogLevel() {
		return $this->logLevel;
	}
}
