<?php

namespace WebVTT\DOM;

use JsonSerializable;
use WebVTT\DOM\Internal\SourceLineTrait;
use WebVTT\DOM\Internal\VttStringableTrait;
use WebVTT\DOM\Internal\VttTextSanitizer;

/**
 * Represents a WebVTT comment block.
 *
 * WebVTT notes (comments) are blocks that are not rendered by the user agent.
 * They are useful for providing information to the author or other tools.
 * A note is identified by the keyword "NOTE" at the beginning of the block.
 */
class VttNote implements VttBlock, JsonSerializable {
	use SourceLineTrait;
	use VttStringableTrait;

	private string $note;

	/**
	 * VttNote constructor.
	 *
	 * @param string $content The note content.
	 */
	public function __construct( string $content ) {
		$this->note = str_replace( "\r\n", "\n", $content );
	}

	/**
	 * Adds a line to the note.
	 *
	 * @param string $content The line to add.
	 */
	public function addLine( $content ): void {
		$this->note .= "\n";
		$this->note .= str_replace( "\r\n", "\n", $content );
	}

	/**
	 * Gets the note content.
	 *
	 * @return string The note content.
	 */
	public function getNote(): string {
		return $this->note;
	}

	/**
	 * Checks if the note is multiline.
	 *
	 * @return bool True if multiline, false otherwise.
	 */
	public function isMultiline(): bool {
		return str_contains( $this->note, "\n" );
	}

	/**
	 * Serializes the note to JSON.
	 *
	 * @return string The serialized note content.
	 */
	public function jsonSerialize(): string {
		return $this->note;
	}

	/**
	 * Returns the note in WebVTT format.
	 *
	 * @return string The note in WebVTT format.
	 */
	public function toVtt(): string {
		// A leading/trailing or embedded blank line ends the block early once
		// concatenated with the "NOTE" header, and NOTE content must not
		// contain "-->" per the WebVTT grammar.
		$note = VttTextSanitizer::sanitizeBlock( $this->note );
		if ( str_contains( $note, "\n" ) ) {
			return "NOTE\n" . $note;
		}
		return 'NOTE ' . $note;
	}
}
