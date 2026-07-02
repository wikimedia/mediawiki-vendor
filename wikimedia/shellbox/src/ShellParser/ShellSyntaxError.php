<?php
declare( strict_types = 1 );

namespace Shellbox\ShellParser;

use Shellbox\ShellboxError;
use Wikimedia\WikiPEG\Location;

/**
 * Exception class for shell command syntax errors. By default the message uses
 * multiple lines to show the exact location of the syntax error.
 */
class ShellSyntaxError extends ShellboxError {
	private string $originalMessage;
	private bool $contextEnabled;

	/**
	 * @internal
	 * @param string $message The message from the PEG
	 * @param Location $location The error location
	 * @param string $input The complete input text
	 */
	public function __construct(
		string $message,
		private readonly Location $location,
		private readonly string $input,
	) {
		parent::__construct( $message );
		$this->originalMessage = $message;
		$this->contextEnabled = true;
		$this->updateMessage();
	}

	/**
	 * Enable multi-line context
	 */
	public function enableContext() {
		$this->contextEnabled = true;
		$this->updateMessage();
	}

	/**
	 * Disable multi-line context
	 */
	public function disableContext() {
		$this->contextEnabled = false;
		$this->updateMessage();
	}

	/**
	 * Update the message (stored in the parent) according to the contextEnabled
	 * setting.
	 */
	private function updateMessage() {
		if ( !$this->contextEnabled ) {
			$this->message = $this->originalMessage;
			return;
		}
		$lines = explode( "\n", $this->input );
		$line = $lines[$this->location->line - 1] ?? '';
		$this->message = $this->originalMessage . "\n" .
			$line . "\n" .
			str_repeat( ' ', $this->location->column - 1 ) . '^';
	}
}
