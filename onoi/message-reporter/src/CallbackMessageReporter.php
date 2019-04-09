<?php

namespace Onoi\MessageReporter;

/**
 * @since 1.4.0
 */
class CallbackMessageReporter implements MessageReporter {

	private $callback;

	public function __construct( callable $callback ) {
		$this->callback = $callback;
	}

	public function reportMessage( $message ) {
		call_user_func( $this->callback, $message );
	}

}