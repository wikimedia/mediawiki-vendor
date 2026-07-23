<?php

namespace WebVTT\Parser;

use WebVTT\DOM\CueText\AnnotatedElementNode;
use WebVTT\DOM\CueText\ElementNode;
use WebVTT\DOM\CueText\Node;
use WebVTT\DOM\CueText\TextNode;
use WebVTT\DOM\CueText\TimestampNode;
use WebVTT\DOM\Enums\CueTag;
use WebVTT\Parser\Exceptions\BadTimeStampException;
use WebVTT\Validation\ValidationReporter;
use WebVTT\Validation\ValidatorTrait;

/**
 * Parses WebVTT cue text into a tree of cue text node objects.
 *
 * This implements the WebVTT cue text parsing rules: literal text becomes
 * TextNode, in-cue timestamps become TimestampNode, and the c/i/b/u/ruby/rt/
 * v/lang tags become ElementNode with their classes and annotation. Parsing is
 * lenient and never fails: unknown tags and unbalanced end tags are ignored.
 * When a reporter is configured the parse doubles as validation, warning about
 * unauthorized tags, unclosed/mismatched/unexpected end tags, voice or
 * language tags missing their annotation, and class names that look like
 * unrecognized colors. Nesting is capped at {@see MAX_NESTING_DEPTH} levels so
 * that pathological input cannot exhaust the call stack of recursive consumers.
 *
 * @see https://www.w3.org/TR/webvtt1/#webvtt-cue-text-parsing-rules
 */
class CueTextParser {
	use ValidatorTrait;

	private const RECOGNIZED_COLORS = [
		'white', 'lime', 'cyan', 'red', 'yellow', 'magenta', 'blue', 'black'
	];

	/**
	 * Cap on tag nesting depth. Real cue text nests only a handful of tags; a
	 * deeper tree only comes from malformed or hostile input, and building it
	 * would let downstream recursive consumers (serialization, DOM rendering)
	 * exhaust the call stack.
	 */
	private const MAX_NESTING_DEPTH = 100;

	private TimeParser $timeParser;

	/**
	 * @param ValidationReporter|null $reporter
	 */
	public function __construct( ?ValidationReporter $reporter = null ) {
		$this->timeParser = new TimeParser();
		$this->setReporter( $reporter );
	}

	/**
	 * @param string $text The cue text.
	 * @return Node[] The parsed cue text node tree.
	 */
	public function parse( string $text ): array {
		$length = strlen( $text );
		$pos = 0;
		/** @var Node[] $result */
		$result = [];
		/** @var ElementNode[] $stack */
		$stack = [];
		// Number of start tags dropped because the nesting cap was reached; used
		// to swallow their matching end tags so they don't look unbalanced.
		$dropped = 0;

		$append = static function ( Node $node ) use ( &$stack, &$result ): void {
			if ( $stack ) {
				end( $stack )->appendChild( $node );
			} else {
				$result[] = $node;
			}
		};

		while ( $pos < $length ) {
			if ( $text[$pos] !== '<' ) {
				$next = strpos( $text, '<', $pos );
				$next = $next === false ? $length : $next;
				$append( new TextNode( $this->unescape( substr( $text, $pos, $next - $pos ) ) ) );
				$pos = $next;
				continue;
			}

			$gt = strpos( $text, '>', $pos );
			$gt = $gt === false ? $length : $gt;
			$inner = substr( $text, $pos + 1, $gt - $pos - 1 );
			$pos = min( $gt + 1, $length );

			if ( $inner === '' ) {
				continue;
			}

			if ( $inner[0] === '/' ) {
				// Balance out the end tags of start tags dropped by the depth cap.
				if ( $dropped > 0 ) {
					$dropped--;
					continue;
				}
				$name = ltrim( substr( $inner, 1 ) );
				if ( CueTag::tryFrom( $name ) === null ) {
					$this->reportWarning( "Unauthorized closing tag in cue text: </$name>" );
					continue;
				}
				if ( !$stack ) {
					$this->reportWarning( "Unexpected closing tag </$name> (stack is empty)" );
					continue;
				}
				$open = end( $stack )->getTag()->value;
				if ( $open === $name ) {
					array_pop( $stack );
				} else {
					$this->reportWarning( "Mismatched closing tag </$name>, expected </$open>" );
				}
				continue;
			}

			if ( ctype_digit( $inner[0] ) ) {
				try {
					$append( new TimestampNode( $this->timeParser->parse( $inner ) ) );
				} catch ( BadTimeStampException $e ) {
					// Ignore malformed timestamp tags.
				}
				continue;
			}

			$annotation = '';
			$head = $inner;
			if ( preg_match( '/[ \t\r\n\f]/', $inner, $m, PREG_OFFSET_CAPTURE ) ) {
				$offset = $m[0][1];
				$head = substr( $inner, 0, $offset );
				$annotation = $this->unescape( trim( substr( $inner, $offset ) ) );
			}
			$parts = explode( '.', $head );
			$tagName = array_shift( $parts );
			$tag = CueTag::tryFrom( $tagName );
			if ( $tag === null ) {
				$this->reportWarning( "Unauthorized tag in cue text: <$inner>" );
				continue;
			}

			if ( count( $stack ) >= self::MAX_NESTING_DEPTH ) {
				if ( $dropped === 0 ) {
					$this->reportWarning(
						'Cue text nesting is too deep; tags beyond ' . self::MAX_NESTING_DEPTH . ' levels are ignored'
					);
				}
				$dropped++;
				continue;
			}

			$classes = array_values( array_filter( $parts, static fn ( $c ) => $c !== '' ) );
			$this->validateClasses( $classes, $inner );

			// Only voice and language tags carry an annotation.
			if ( $tag === CueTag::VOICE || $tag === CueTag::LANGUAGE ) {
				if ( $annotation === '' ) {
					$label = $tag === CueTag::VOICE ? 'voice name' : 'language annotation';
					$this->reportWarning( "Missing $label for tag <$inner>" );
				}
				$node = new AnnotatedElementNode( $tag, $classes, $annotation );
			} else {
				$node = new ElementNode( $tag, $classes );
			}
			$append( $node );
			$stack[] = $node;
		}

		// Report tags left open, except that the spec permits omitting a single
		// trailing voice end tag.
		if ( $stack && !( count( $stack ) === 1 && reset( $stack )->getTag() === CueTag::VOICE ) ) {
			foreach ( array_reverse( $stack ) as $openNode ) {
				$this->reportWarning( 'Unclosed tag: <' . $openNode->getTag()->value . '>' );
			}
		}

		return $result;
	}

	/**
	 * Warns about class names that look like colors but are not among the eight
	 * WebVTT-recognized names, flagging both `bg_`-prefixed backgrounds and near
	 * misses such as the common `green` (which should be `lime`).
	 *
	 * @param string[] $classes
	 * @param string $inner The raw tag content, for the warning message.
	 */
	private function validateClasses( array $classes, string $inner ): void {
		foreach ( $classes as $class ) {
			if ( str_starts_with( $class, 'bg_' ) ) {
				$color = substr( $class, 3 );
				if ( !in_array( $color, self::RECOGNIZED_COLORS, true ) ) {
					$this->reportWarning( "Unrecognized background color class '$class' in tag <$inner>" );
				}
			} elseif ( !in_array( $class, self::RECOGNIZED_COLORS, true )
				&& preg_match( '/^(?:green|grey|gray|silver|maroon|olive|teal|navy|purple|fuchsia)$/i', $class )
			) {
				$hint = strtolower( $class ) === 'green' ? 'lime' : 'one of the 8 standard colors';
				$this->reportWarning( "Unrecognized color class '$class' in tag <$inner>. Did you mean '$hint'?" );
			}
		}
	}

	/**
	 * @param string $text
	 * @return string
	 */
	private function unescape( string $text ): string {
		return strtr( $text, [
			'&amp;' => '&',
			'&lt;' => '<',
			'&gt;' => '>',
			'&lrm;' => "\u{200E}",
			'&rlm;' => "\u{200F}",
			'&nbsp;' => "\u{00A0}",
		] );
	}
}
