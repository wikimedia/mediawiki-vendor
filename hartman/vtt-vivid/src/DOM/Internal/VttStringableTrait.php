<?php

namespace WebVTT\DOM\Internal;

/**
 * Provides the string form of a WebVTT object as its WebVTT serialization.
 *
 * @see \WebVTT\DOM\VttBlock::toVtt()
 */
trait VttStringableTrait {

	abstract public function toVtt(): string;

	public function __toString(): string {
		return $this->toVtt();
	}
}
