<?php

namespace Wikimedia\RemexHtml\Tokenizer;

/**
 * The handler which converts events to tokens arrays for TokenGenerator
 */
class TokenGeneratorHandler implements TokenHandler {
	/** @var array[] */
	public $tokens = [];

	/** @inheritDoc */
	public function startDocument( Tokenizer $tokenizer, $fragmentNamespace, $fragmentName ) {
		$this->tokens[] = [
			'type' => 'startDocument',
			'fragmentNamespace' => $fragmentNamespace,
			'fragmentName' => $fragmentName
		];
	}

	/** @inheritDoc */
	public function endDocument( $pos ) {
		$this->tokens[] = [ 'type' => 'endDocument' ];
	}

	/** @inheritDoc */
	public function error( $text, $pos ) {
		$this->tokens[] = [
			'type' => 'error',
			'text' => $text,
			'sourceStart' => $pos
		];
	}

	/** @inheritDoc */
	public function characters( $text, $start, $length, $sourceStart, $sourceLength ) {
		$this->tokens[] = [
			'type' => 'text',
			'text' => $text,
			'start' => $start,
			'length' => $length,
			'sourceStart' => $sourceStart,
			'sourceLength' => $sourceLength ];
	}

	/** @inheritDoc */
	public function startTag( $name, Attributes $attrs, $selfClose, $sourceStart, $sourceLength ) {
		$this->tokens[] = [
			'type' => 'startTag',
			'name' => $name,
			'attrs' => $attrs,
			'selfClose' => $selfClose,
			'sourceStart' => $sourceStart,
			'sourceLength' => $sourceLength ];
	}

	/** @inheritDoc */
	public function endTag( $name, $sourceStart, $sourceLength ) {
		$this->tokens[] = [
			'type' => 'endTag',
			'name' => $name,
			'sourceStart' => $sourceStart,
			'sourceLength' => $sourceLength ];
	}

	/** @inheritDoc */
	public function doctype( $name, $public, $system, $quirks, $sourceStart, $sourceLength ) {
		$this->tokens[] = [
			'type' => 'doctype',
			'name' => $name,
			'public' => $public,
			'system' => $system,
			'quirks' => $quirks ];
	}

	/** @inheritDoc */
	public function comment( $text, $sourceStart, $sourceLength ) {
		$this->tokens[] = [
			'type' => 'comment',
			'text' => $text,
			'sourceStart' => $sourceStart,
			'sourceLength' => $sourceLength ];
	}
}
