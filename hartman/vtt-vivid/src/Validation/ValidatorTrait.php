<?php

namespace WebVTT\Validation;

/**
 * Trait to provide validation reporting capabilities.
 */
trait ValidatorTrait {
	protected ?ValidationReporter $reporter = null;

	/**
	 * Sets the validation reporter.
	 *
	 * @param ValidationReporter|null $reporter
	 */
	public function setReporter( ?ValidationReporter $reporter ): void {
		$this->reporter = $reporter;
	}

	/**
	 * Gets the validation reporter.
	 *
	 * @return ValidationReporter|null
	 */
	public function getReporter(): ?ValidationReporter {
		return $this->reporter;
	}

	/**
	 * Reports a validation warning.
	 *
	 * @param string $message
	 */
	protected function reportWarning( string $message ): void {
		if ( $this->reporter ) {
			$this->reporter->report( $message );
		}
	}
}
