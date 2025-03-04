<?php

namespace SmashPig\Core;

/**
 * Class ConfigurationKeyException
 * @package SmashPig\Core
 *
 * Exception thrown when a configuration key is not valid or has some other problem.
 */
class ConfigurationKeyException extends ConfigurationException {
	public $key;

	public function __construct( $message = null, $key = null, $previous = null ) {
		parent::__construct( $message, 0, $previous );
		$this->key = $key;
	}
}
