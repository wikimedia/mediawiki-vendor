<?php

namespace WebVTT\DOM;

use Stringable;

/**
 * Represents a generic WebVTT block.
 *
 * In the WebVTT file structure, almost everything after the "WEBVTT" signature
 * is organized into blocks. A block is a sequence of non-empty lines,
 * and blocks are separated from each other by one or more empty lines.
 *
 * Common block types include Cues, Regions, Styles, and Notes. A block's string
 * form is its WebVTT serialization ({@see toVtt()}).
 */
interface VttBlock extends Stringable {
	/**
	 * Returns the block in WebVTT format.
	 *
	 * @return string The block in WebVTT format.
	 */
	public function toVtt(): string;

	/**
	 * Sets the line number where this block started.
	 *
	 * @param int $line The line number.
	 */
	public function setSourceLine( int $line ): void;

	/**
	 * Gets the line number where this block started.
	 *
	 * @return int The line number.
	 */
	public function getSourceLine(): int;
}
