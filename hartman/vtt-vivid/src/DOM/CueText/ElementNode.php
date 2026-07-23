<?php

namespace WebVTT\DOM\CueText;

use WebVTT\DOM\Enums\CueTag;

/**
 * A WebVTT cue text element node: a tag that can contain other nodes.
 *
 * The tag is one of the {@see CueTag} cases that carry no annotation
 * (c, i, b, u, ruby, rt). The voice (v) and language (lang) tags are
 * represented by {@see AnnotatedElementNode} instead.
 */
class ElementNode extends Node {
	private CueTag $tag;
	/** @var string[] */
	private array $classes;
	/** @var Node[] */
	private array $children = [];

	/**
	 * @param CueTag $tag
	 * @param string[] $classes
	 */
	public function __construct( CueTag $tag, array $classes = [] ) {
		$this->tag = $tag;
		$this->classes = $classes;
	}

	/**
	 * @return CueTag
	 */
	public function getTag(): CueTag {
		return $this->tag;
	}

	/**
	 * @return string[]
	 */
	public function getClasses(): array {
		return $this->classes;
	}

	/**
	 * @return Node[]
	 */
	public function getChildren(): array {
		return $this->children;
	}

	/**
	 * @param Node $node
	 */
	public function appendChild( Node $node ): void {
		$this->children[] = $node;
	}

	public function jsonSerialize(): array {
		return [
			'type' => $this->tag->value,
			'classes' => $this->classes,
			'children' => $this->children,
		];
	}

	public function toVtt(): string {
		$out = '<' . $this->openTag() . '>';
		foreach ( $this->children as $child ) {
			$out .= $child->toVtt();
		}
		return $out . '</' . $this->tag->value . '>';
	}

	/**
	 * Builds the content of the start tag (name and classes). Subclasses that
	 * carry more data extend this.
	 *
	 * @return string
	 */
	protected function openTag(): string {
		$open = $this->tag->value;
		foreach ( $this->classes as $class ) {
			$open .= '.' . $class;
		}
		return $open;
	}
}
