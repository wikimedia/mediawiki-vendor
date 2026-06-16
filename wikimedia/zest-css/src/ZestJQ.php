<?php
declare( strict_types = 1 );

namespace Wikimedia\Zest;

use Closure;
use DOMDocument;
use DOMDocumentFragment;
use DOMElement;
use DOMNode;
use InvalidArgumentException;
use Wikimedia\ZestJQ\JQ;
use Wikimedia\ZestJQ\JQEnv;
use Wikimedia\ZestJQ\JQError;
use Wikimedia\ZestJQ\JQHaltException;
use Wikimedia\ZestJQ\JQUtils;

/**
 * JQ-style JSON selectors for Zest CSS selectors.
 *
 * Syntax: [attr/jq_query]
 * The attr value is parsed as JSON and the JQ query is evaluated against it.
 * The element matches if the query returns any truthy result.
 *
 * Supports a substantial subset of the JQ language including pipes, path
 * traversal, comparisons, and many built-in functions.
 *
 * @see https://jqlang.org/manual/
 */
class ZestJQ {
	/** @var array<string,ZestInst> */
	private static array $instances = [];

	/** @var array<string,Closure> */
	private array $cache = [];
	private ?JQEnv $env = null;

	public static function register( ZestInst $zest ): ZestInst {
		// Create new cache for fast evaluation of compiled expressions
		$jq = new static();
		// Register it with Zest
		$zest->addOperator( '/', $jq->cachedEval( ... ) );
		// Fluently return the argument
		return $zest;
	}

	/**
	 * This method can be overridden in a subclass if you want to
	 * use an extended standard library to evaluate your JQ expressions.
	 * @see `JQEnv::extendEnv()`.
	 */
	protected function extendEnvironment( JQEnv $env ): JQEnv {
		// By default, use the standard environment.
		return $env;
	}

	private static function singleton(): ZestInst {
		self::$instances[static::class] ??= static::register( new ZestInst );
		return self::$instances[static::class];
	}

	/**
	 * Find elements matching a CSS selector underneath $context.
	 * @param string $sel The CSS selector string
	 * @param DOMDocument|DOMDocumentFragment|DOMElement $context
	 *   The scoping root for the search
	 * @param array $opts Additional match-context options (optional)
	 * @return array Elements matching the CSS selector
	 */
	public static function find( string $sel, $context, array $opts = [] ): array {
		return self::singleton()->find( $sel, $context, $opts );
	}

	/**
	 * Determine whether an element matches the given selector.
	 * @param DOMNode $el The element to be tested
	 * @param string $sel The CSS selector string
	 * @param array $opts Additional match-context options (optional)
	 * @return bool True iff the element matches the selector
	 */
	public static function matches( $el, string $sel, array $opts = [] ): bool {
		return self::singleton()->matches( $el, $sel, $opts );
	}

	private function cachedEval( string $attrValue, string $jqExpr ): bool {
		$eval = $this->cache[$jqExpr] ?? null;
		if ( $eval === null ) {
			$this->env ??= $this->extendEnvironment( JQEnv::getStdEnv() );
			$eval = $this->cache[$jqExpr] = self::parse( $jqExpr, $this->env );
		}
		try {
			$val = JQUtils::jsonDecode( $attrValue );
			return $eval( $val );
		} catch ( JQError | JQHaltException ) {
			// Bad JSON, evaluation error, or halt — treat as non-matching
			return false;
		}
	}

	private static function parse( string $jqExpr, JQEnv $env ): Closure {
		try {
			$f = JQ::compile( $jqExpr, 'JQ selector', $env );
		} catch ( JQError ) {
			throw new InvalidArgumentException( "Bad JQ selector: {$jqExpr}" );
		}
		// The filter evaluates to true if: there's more than one result,
		// or it yields one result and that result is truthy.
		return static function ( $json ) use ( $f ) {
			$sawOne = false;
			foreach ( $f( $json ) as $val ) {
				if ( $sawOne || JQUtils::toBoolean( $val ) ) {
					return true;
				}
				$sawOne = true;
			}
			return false;
		};
	}

}
