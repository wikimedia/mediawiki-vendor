<?php

namespace Wikimedia\RemexHtml\TreeBuilder;

use Wikimedia\RemexHtml\Tokenizer\Attributes;
use Wikimedia\RemexHtml\Tokenizer\TokenHandler;
use Wikimedia\RemexHtml\Tokenizer\Tokenizer;

/**
 * This is a debugging helper class which calls a callback function with a
 * descriptive message each time a token event comes from the Tokenizer. The
 * messages include information about the current state and transitions of the
 * Dispatcher which is the next stage in the pipeline.
 */
class DispatchTracer implements TokenHandler {
	/** @var string */
	private $input;
	private Dispatcher $dispatcher;
	/** @var callable */
	private $callback;

	public function __construct( string $input, Dispatcher $dispatcher, callable $callback ) {
		$this->input = $input;
		$this->dispatcher = $dispatcher;
		$this->callback = $callback;
	}

	private function trace( string $msg ) {
		( $this->callback )( "[Dispatch] $msg" );
	}

	private function excerpt( string $text ): string {
		if ( strlen( $text ) > 20 ) {
			$text = substr( $text, 0, 20 ) . '...';
		}
		return str_replace( "\n", "\\n", $text );
	}

	private function wrap( string $funcName, int $sourceStart, int $sourceLength, array $args ) {
		$prevHandler = $this->getHandlerName();
		$excerpt = $this->excerpt( substr( $this->input, $sourceStart, $sourceLength ) );
		$msg = "$funcName $prevHandler \"$excerpt\"";
		$this->trace( $msg );
		$this->dispatcher->$funcName( ...$args );
		$handler = $this->getHandlerName();
		if ( $prevHandler !== $handler ) {
			$this->trace( "$prevHandler -> $handler" );
		}
	}

	private function getHandlerName(): string {
		$handler = $this->dispatcher->getHandler();
		$name = $handler ? get_class( $handler ) : 'NULL';
		$slashPos = strrpos( $name, '\\' );
		if ( $slashPos === false ) {
			return $name;
		} else {
			return substr( $name, $slashPos + 1 );
		}
	}

	/** @inheritDoc */
	public function startDocument( Tokenizer $tokenizer, $ns, $name ) {
		$prevHandler = $this->getHandlerName();
		$nsMsg = $ns === null ? 'NULL' : $ns;
		$nameMsg = $name === null ? 'NULL' : $name;
		$this->trace( "startDocument $prevHandler $nsMsg $nameMsg" );
		$this->dispatcher->startDocument( $tokenizer, $ns, $name );
		$handler = $this->getHandlerName();
		if ( $prevHandler !== $handler ) {
			$this->trace( "$prevHandler -> $handler" );
		}
	}

	/** @inheritDoc */
	public function endDocument( $pos ) {
		$this->wrap( __FUNCTION__, $pos, 0, func_get_args() );
	}

	/** @inheritDoc */
	public function error( $text, $pos ) {
		$handler = $this->getHandlerName();
		$this->trace( "error $handler \"$text\"" );
		$this->dispatcher->error( $text, $pos );
	}

	/** @inheritDoc */
	public function characters( $text, $start, $length, $sourceStart, $sourceLength ) {
		$this->wrap( __FUNCTION__, $sourceStart, $sourceLength, func_get_args() );
	}

	/** @inheritDoc */
	public function startTag( $name, Attributes $attrs, $selfClose, $sourceStart, $sourceLength ) {
		$this->wrap( __FUNCTION__, $sourceStart, $sourceLength, func_get_args() );
	}

	/** @inheritDoc */
	public function endTag( $name, $sourceStart, $sourceLength ) {
		$this->wrap( __FUNCTION__, $sourceStart, $sourceLength, func_get_args() );
	}

	/** @inheritDoc */
	public function doctype( $name, $public, $system, $quirks, $sourceStart, $sourceLength ) {
		$this->wrap( __FUNCTION__, $sourceStart, $sourceLength, func_get_args() );
	}

	/** @inheritDoc */
	public function comment( $text, $sourceStart, $sourceLength ) {
		$this->wrap( __FUNCTION__, $sourceStart, $sourceLength, func_get_args() );
	}
}
