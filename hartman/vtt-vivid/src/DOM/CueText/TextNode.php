<?php

namespace WebVTT\DOM\CueText;

/**
 * A WebVTT cue text node holding literal text.
 */
class TextNode extends Node {
	private string $value;

	/**
	 * @param string $value The (unescaped) text.
	 */
	public function __construct( string $value ) {
		$this->value = $value;
	}

	/**
	 * @return string The (unescaped) text.
	 */
	public function getValue(): string {
		return $this->value;
	}

	public function jsonSerialize(): array {
		return [ 'type' => 'text', 'value' => $this->value ];
	}

	/**
	 * Escapes the structurally significant characters. A bare '>' is legal in
	 * cue text, so it is left as-is for better round-tripping.
	 */
	public function toVtt(): string {
		return strtr( $this->value, [ '&' => '&amp;', '<' => '&lt;' ] );
	}
}
