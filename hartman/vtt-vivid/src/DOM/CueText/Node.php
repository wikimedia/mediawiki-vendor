<?php

namespace WebVTT\DOM\CueText;

use JsonSerializable;
use Stringable;

/**
 * Base class for WebVTT cue text node objects.
 *
 * The cue text of a VttCue can be parsed into a tree of these nodes, following
 * the WebVTT cue text parsing rules. Each node serializes itself back to cue
 * text via {@see toVtt()}, which is also its string form.
 *
 * @see https://www.w3.org/TR/webvtt1/#webvtt-cue-text-parsing-rules
 */
abstract class Node implements JsonSerializable, Stringable {

	/**
	 * Returns this node as canonical WebVTT cue text.
	 *
	 * @return string
	 */
	abstract public function toVtt(): string;

	public function __toString(): string {
		return $this->toVtt();
	}
}
