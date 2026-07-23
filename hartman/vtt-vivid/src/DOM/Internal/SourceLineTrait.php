<?php

namespace WebVTT\DOM\Internal;

/**
 * Trait to provide source line tracking for WebVTT blocks.
 */
trait SourceLineTrait {
	protected int $sourceLine = 0;

	/**
	 * Sets the line number where this block started.
	 *
	 * @param int $line The line number.
	 */
	public function setSourceLine( int $line ): void {
		$this->sourceLine = $line;
	}

	/**
	 * Gets the line number where this block started.
	 *
	 * @return int The line number.
	 */
	public function getSourceLine(): int {
		return $this->sourceLine;
	}
}
