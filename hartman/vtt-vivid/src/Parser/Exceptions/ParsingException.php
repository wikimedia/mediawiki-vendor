<?php

namespace WebVTT\Parser\Exceptions;

class ParsingException extends \Exception {
	protected int $sourceLine = 0;

	public function setSourceLine( int $line ): void {
		$this->sourceLine = $line;
	}

	public function getSourceLine(): int {
		return $this->sourceLine;
	}

	public function getName(): string {
		return $this->__toString();
	}

	public function __toString(): string {
		return self::class;
	}
}
