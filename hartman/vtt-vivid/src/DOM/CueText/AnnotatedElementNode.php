<?php

namespace WebVTT\DOM\CueText;

use WebVTT\DOM\Enums\CueTag;

/**
 * A WebVTT cue text element node that carries an annotation.
 *
 * Only the voice (v) and language (lang) tags have an annotation: the speaker
 * name for v, the language for lang.
 */
class AnnotatedElementNode extends ElementNode {
	private string $annotation;

	/**
	 * @param CueTag $tag One of CueTag::VOICE or CueTag::LANGUAGE.
	 * @param string[] $classes
	 * @param string $annotation
	 */
	public function __construct( CueTag $tag, array $classes = [], string $annotation = '' ) {
		parent::__construct( $tag, $classes );
		$this->annotation = $annotation;
	}

	/**
	 * @return string The annotation (voice name / language), or '' if none.
	 */
	public function getAnnotation(): string {
		return $this->annotation;
	}

	public function jsonSerialize(): array {
		return [
			'type' => $this->getTag()->value,
			'classes' => $this->getClasses(),
			'annotation' => $this->annotation,
			'children' => $this->getChildren(),
		];
	}

	protected function openTag(): string {
		$open = parent::openTag();
		if ( $this->annotation !== '' ) {
			// An annotation additionally must not contain '>'.
			$open .= ' ' . strtr( $this->annotation, [ '&' => '&amp;', '<' => '&lt;', '>' => '&gt;' ] );
		}
		return $open;
	}
}
