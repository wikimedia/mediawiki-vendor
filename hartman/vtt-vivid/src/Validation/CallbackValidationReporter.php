<?php

namespace WebVTT\Validation;

use Closure;

/**
 * A validation reporter that wraps a closure callback.
 */
class CallbackValidationReporter implements ValidationReporter {
	private ?Closure $callback;

	/**
	 * @param Closure|null $callback
	 */
	public function __construct( ?Closure $callback ) {
		$this->callback = $callback;
	}

	/**
	 * @inheritDoc
	 */
	public function report( string $message ): void {
		if ( $this->callback ) {
			( $this->callback )( $message );
		}
	}
}
