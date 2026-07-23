<?php

namespace WebVTT\DOM;

use JsonSerializable;
use WebVTT\DOM\Internal\SourceLineTrait;
use WebVTT\DOM\Internal\VttStringableTrait;
use WebVTT\DOM\Internal\VttTextSanitizer;

/**
 * Represents a WebVTT STYLE block.
 *
 * A style block contains CSS rules to style WebVTT cues. The CSS can target
 * specific cue tags or classes using the ::cue pseudo-element.
 * Style blocks must appear before any cue blocks in the WebVTT file.
 */
class VttStyle implements VttBlock, JsonSerializable {
	use SourceLineTrait;
	use VttStringableTrait;

	private string $style;

	/**
	 * VttStyle constructor.
	 *
	 * @param string|null $style The style content.
	 */
	public function __construct( ?string $style ) {
		$this->style = $style;
	}

	/**
	 * Sets the style content.
	 *
	 * @param string $style The style content.
	 */
	public function setStyle( string $style ): void {
		$this->style = $style;
	}

	/**
	 * Adds a line to the style block.
	 *
	 * @param string $content The line to add.
	 */
	public function addLine( string $content ): void {
		$this->style .= "\n";
		$this->style .= $content;
	}

	/**
	 * Gets the style content.
	 *
	 * @return string The style content.
	 */
	public function getStyle(): string {
		return $this->style;
	}

	/**
	 * Serializes the style block to JSON.
	 *
	 * @return string The serialized style content.
	 */
	public function jsonSerialize(): string {
		return $this->style;
	}

	/**
	 * Returns the style block in WebVTT format.
	 *
	 * @return string The style block in WebVTT format.
	 */
	public function toVtt(): string {
		// A leading/trailing or embedded blank line ends the block early once
		// concatenated with the "STYLE" header, and STYLE content must not
		// contain "-->" per the WebVTT grammar.
		return "STYLE\n" . VttTextSanitizer::sanitizeBlock( $this->style );
	}
}
