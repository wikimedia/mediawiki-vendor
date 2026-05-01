<?php
declare( strict_types=1 );

/**
 * @license GPL-2.0-or-later
 * @file
 */

namespace Wikimedia\JsonCodec;

use Stringable;

/**
 * Class hints with modifiers.
 * @template T
 */
class Hint implements Stringable {

	/** @see HintType::DEFAULT */
	public const DEFAULT = HintType::DEFAULT;
	/** @see HintType::LIST */
	public const LIST = HintType::LIST;
	/** @see HintType::STDCLASS */
	public const STDCLASS = HintType::STDCLASS;
	/** @see HintType::USE_SQUARE */
	public const USE_SQUARE = HintType::USE_SQUARE;
	/** @see HintType::ALLOW_OBJECT */
	public const ALLOW_OBJECT = HintType::ALLOW_OBJECT;
	/** @see HintType::INHERITED */
	public const INHERITED = HintType::INHERITED;
	/** @see HintType::ONLY_FOR_DECODE */
	public const ONLY_FOR_DECODE = HintType::ONLY_FOR_DECODE;

	/**
	 * Create a new serialization class type hint.
	 * @param class-string<T>|Hint<T> $parent
	 * @param HintType $modifier A hint modifier
	 */
	public function __construct(
		/** @var class-string<T>|Hint<T> */
		public readonly string|Hint $parent,
		public readonly HintType $modifier = HintType::DEFAULT,
	) {
	}

	/**
	 * Helper function to create nested hints.  For example, the
	 * `Foo[][]` type can be created as
	 * `Hint::build(Foo::class, Hint:LIST, Hint::LIST)`.
	 *
	 * Note that, in the grand (?) tradition of C-like types,
	 * modifiers are read right-to-left.  That is, a "stdClass containing
	 * values which are lists of Foo" is written 'backwards' as:
	 * `Hint::build(Foo::class, Hint::LIST, Hint::STDCLASS)`.
	 *
	 * @template T
	 * @param class-string<T>|Hint<T> $classNameOrHint
	 * @param HintType ...$modifiers
	 * @return class-string<T>|Hint<T>
	 */
	public static function build( string|Hint $classNameOrHint, HintType ...$modifiers ) {
		if ( count( $modifiers ) === 0 ) {
			return $classNameOrHint;
		}
		$last = array_pop( $modifiers );
		return new Hint( self::build( $classNameOrHint, ...$modifiers ), $last );
	}

	/**
	 * Return true if the hint $a is the same as the hint $b.
	 * @param class-string|Hint $a
	 * @param class-string|Hint $b
	 * @return bool
	 */
	public static function isSame( string|Hint $a, string|Hint $b ): bool {
		if ( is_string( $a ) ) {
			return is_string( $b ) && ( $a === $b );
		}
		return ( $b instanceof Hint ) && ( $a->modifier === $b->modifier ) &&
			self::isSame( $a->parent, $b->parent );
	}

	public function __toString(): string {
		$parent = strval( $this->parent );
		return "{$this->modifier->name}({$parent})";
	}
}
