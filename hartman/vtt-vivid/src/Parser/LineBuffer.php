<?php

namespace WebVTT\Parser;

/**
 * Manages an internal buffer for line-based processing of WebVTT data.
 *
 * Consumed data is tracked with a read cursor rather than being spliced off the
 * front of the buffer on every line, so parsing a large chunk stays linear in
 * its length instead of quadratic. The consumed prefix is dropped when new data
 * is appended, bounding memory to the unprocessed remainder.
 *
 * Line terminators follow the WebVTT specification: CRLF, a lone LF, or a lone
 * CR. A CR at the end of the buffer is held back until the following byte is
 * known, so a CRLF pair split across two appended chunks still counts as one
 * terminator rather than yielding a spurious empty line.
 */
class LineBuffer {
	private string $buffer = '';
	private int $offset = 0;
	private bool $alreadyCollectedLine = false;
	private int $lineNumber = 0;

	/**
	 * Append data to the internal buffer
	 *
	 * @param string $data The data to append
	 */
	public function append( string $data ): void {
		if ( $this->offset > 0 ) {
			$this->buffer = substr( $this->buffer, $this->offset );
			$this->offset = 0;
		}
		$this->buffer .= $data;
	}

	/**
	 * Check if the buffer has a complete line
	 *
	 * @return bool True if the buffer contains a line ending
	 */
	public function hasCompleteLine(): bool {
		$length = strlen( $this->buffer );
		$span = strcspn( $this->buffer, "\r\n", $this->offset );
		$terminator = $this->offset + $span;
		if ( $terminator >= $length ) {
			return false;
		}
		// A lone CR at the very end of the buffer may be the first half of a
		// CRLF pair whose LF has not been appended yet. Withhold the line until
		// more data arrives so the pair is consumed as a single terminator
		// rather than splitting into a line plus a spurious empty one.
		if ( $this->buffer[$terminator] === "\r" && $terminator === $length - 1 ) {
			return false;
		}
		return true;
	}

	/**
	 * Collect the next line from the buffer
	 *
	 * @return string The next line from the buffer
	 */
	public function collectNextLine(): string {
		$length = strlen( $this->buffer );
		$span = strcspn( $this->buffer, "\r\n", $this->offset );
		$line = substr( $this->buffer, $this->offset, $span );

		$pos = $this->offset + $span;
		if ( $pos < $length ) {
			if ( $this->buffer[$pos] === "\r" ) {
				++$pos;
			}
			if ( $pos < $length && $this->buffer[$pos] === "\n" ) {
				++$pos;
			}
		}

		$this->offset = $pos;
		$this->lineNumber++;
		return $line;
	}

	/**
	 * Get the current line number
	 *
	 * @return int The current line number
	 */
	public function getLineNumber(): int {
		return $this->lineNumber;
	}

	/**
	 * Check if the buffer is empty
	 *
	 * @return bool True if the buffer is empty
	 */
	public function isEmpty(): bool {
		return $this->offset >= strlen( $this->buffer );
	}

	/**
	 * Get the current buffer content
	 *
	 * @return string The current buffer content
	 */
	public function getBuffer(): string {
		return substr( $this->buffer, $this->offset );
	}

	/**
	 * Set the already collected line flag
	 *
	 * @param bool $value The flag value
	 */
	public function setAlreadyCollectedLine( bool $value ): void {
		$this->alreadyCollectedLine = $value;
	}

	/**
	 * Get the already collected line flag
	 *
	 * @return bool The flag value
	 */
	public function alreadyCollectedLine(): bool {
		return $this->alreadyCollectedLine;
	}
}
