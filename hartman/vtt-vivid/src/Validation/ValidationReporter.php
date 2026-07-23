<?php

namespace WebVTT\Validation;

/**
 * Interface for reporting validation issues.
 */
interface ValidationReporter {
	/**
	 * Reports a validation issue.
	 *
	 * @param string $message The warning message.
	 */
	public function report( string $message ): void;
}
