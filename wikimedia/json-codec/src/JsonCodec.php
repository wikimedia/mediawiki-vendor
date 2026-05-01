<?php
declare( strict_types=1 );

/**
 * @license GPL-2.0-or-later
 * @file
 */

namespace Wikimedia\JsonCodec;

use Exception;
use InvalidArgumentException;
use Psr\Container\ContainerInterface;
use Psr\Container\NotFoundExceptionInterface;
use ReflectionClass;
use stdClass;
use UnitEnum;

/**
 * Helper class to serialize/unserialize things to/from JSON.
 */
class JsonCodec implements JsonCodecInterface {
	/** @var ContainerInterface Service container */
	protected readonly ContainerInterface $serviceContainer;

	/** @var array<class-string,JsonClassCodec> Class codecs */
	protected array $codecs = [];

	/**
	 * Name of the property where class information is stored; it also
	 * is used to mark "complex" arrays, and as a place to store the contents
	 * of any pre-existing array property that happened to have the same name.
	 */
	protected const TYPE_ANNOTATION = '_type_';

	/**
	 * Prefix used to distinguish abbreviations from class names.
	 */
	protected const ABBREV_PREFIX = '@';

	/**
	 * Maps abbreviation names to hint abbreviations.  Keys are prefixed
	 * with self::ABBREV_PREFIX for faster lookup.
	 * @see ::addAbbrev()
	 * @var array<string,Abbrev>
	 */
	protected array $abbrevToHintMap = [];

	/**
	 * Maps PHP class names to abbreviations
	 * @see ::addAbbrev()
	 * @var array<class-string,Abbrev>
	 */
	protected array $classToAbbrevMap = [];

	/**
	 * @param ?ContainerInterface $serviceContainer
	 */
	public function __construct( ?ContainerInterface $serviceContainer = null ) {
		$this->serviceContainer = $serviceContainer ??
			// Use an empty container if none is provided.
			new class implements ContainerInterface {
				/**
				 * @param string $id
				 * @return never
				 */
				public function get( $id ) {
					throw new class( "not found" ) extends Exception implements NotFoundExceptionInterface {
					};
				}

				/** @inheritDoc */
				public function has( string $id ): bool {
					return false;
				}
			};
		$this->addCodecFor(
			stdClass::class, JsonStdClassCodec::getInstance()
		);
	}

	/**
	 * Recursively converts a given object to a JSON-encoded string.
	 * While serializing the $value JsonCodec delegates to the appropriate
	 * JsonClassCodecs of any classes which implement JsonCodecable.
	 *
	 * If a $classHint is provided and matches the type of the value,
	 * then type information will not be included in the generated JSON;
	 * otherwise an appropriate class name will be added to the JSON to
	 * guide deserialization.
	 *
	 * @param mixed|null $value
	 * @param class-string|Hint|Abbrev|null $classHint An optional hint to
	 *   the type of the encoded object.  If this is provided and matches
	 *   the type of $value, then explicit type information will be omitted
	 *   from the generated JSON, which saves some space.
	 * @return string
	 */
	public function toJsonString( $value, $classHint = null ): string {
		return json_encode(
			$this->toJsonArray( $value, $classHint ),
			JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE |
			JSON_HEX_TAG | JSON_HEX_AMP
		);
	}

	/**
	 * Recursively converts a JSON-encoded string to an object value or scalar.
	 * While deserializing the $json JsonCodec delegates to the appropriate
	 * JsonClassCodecs of any classes which implement JsonCodecable.
	 *
	 * For objects encoded using implicit class information, a "class hint"
	 * can be provided to guide deserialization; this is unnecessary for
	 * objects serialized with explicit classes.
	 *
	 * @param string $json A JSON-encoded string
	 * @param class-string|Hint|Abbrev|null $classHint An optional hint to
	 *   the type of the encoded object.  In the absence of explicit
	 *   type information in the JSON, this will be used as the type of
	 *   the created object.
	 * @return mixed|null
	 */
	public function newFromJsonString( $json, $classHint = null ) {
		return $this->newFromJsonArray(
			json_decode( $json, true ), $classHint
		);
	}

	/**
	 * Maintain a cache giving the codec for a given class name.
	 *
	 * Reusing this JsonCodec object will also reuse this cache, which
	 * could improve performance somewhat.
	 *
	 * @param class-string $className
	 * @return ?JsonClassCodec a codec for the class, or null if the class is
	 *   not serializable.
	 */
	protected function codecFor( string $className ): ?JsonClassCodec {
		$codec = $this->codecs[$className] ?? null;
		if ( $codec !== null ) {
			return $codec;
		}
		// Check for class aliases to ensure we don't use split codecs
		$trueName = ( new ReflectionClass( $className ) )->getName();
		if ( $trueName !== $className ) {
			$codec = $this->codecs[$trueName] ?? null;
			if ( $codec !== null ) {
				$this->codecs[$className] = $codec;
				return $codec;
			}
			$className = $trueName;
		}
		if ( is_a( $className, JsonCodecable::class, true ) ) {
			$codec = $className::jsonClassCodec( $this, $this->serviceContainer );
			$this->codecs[$className] = $codec;
		} elseif ( is_a( $className, UnitEnum::class, true ) ) {
			$codec = JsonEnumClassCodec::getInstance();
			$this->codecs[$className] = $codec;
		}
		return $codec;
	}

	/**
	 * Allow the use of a customized encoding for the given class; the given
	 * className need not be a JsonCodecable and if it *does* correspond to
	 * a JsonCodecable it will override the class codec specified by the
	 * JsonCodecable.
	 * @param class-string $className
	 * @param JsonClassCodec $codec A codec to use for $className
	 */
	public function addCodecFor( string $className, JsonClassCodec $codec ): void {
		// Resolve aliases
		$className = ( new ReflectionClass( $className ) )->getName();
		// Sanity check
		if ( isset( $this->codecs[$className] ) ) {
			throw new InvalidArgumentException(
				"Codec already present for $className"
			);
		}
		$this->codecs[$className] = $codec;
	}

	/**
	 * This supports cross-platform schemas by decoupling a hint from
	 * the actual PHP class name.  If the `_type_` specified in the
	 * JSON starts with `self::ABBREV_PREFIX` it is looked up in the
	 * abbreviation map to obtain a hint.
	 *
	 * Only abbreviations that map to `class-string` are used for encoding.
	 * If you wish to add an `class-string` abbreviation that is only for
	 * decode, then building a simple Hint with ONLY_FOR_DECODE is sufficient:
	 * ```
	 * $codec->addAbbrev($name, Hint::build($className, HintType::ONLY_FOR_DECODE));
	 * ```
	 * This allows for forward-compatibility with a future encoding which
	 * uses abbreviations (as usual for the ONLY_FOR_DECODE hint), but it
	 * can also be used to allow multiple abbreviations for the same class
	 * name, as long at most one of them is registered without
	 * ONLY_FOR_DECODE.
	 * @param string $name The abbreviation name (unprefixed)
	 * @param class-string|Hint $hint The PHP class name or hint
	 *  to be abbreviated
	 */
	public function addAbbrev( string $name, string|Hint $hint ): Abbrev {
		$abbrev = new Abbrev( $name, $hint );
		// Each abbreviation must map to a single class name for decode,
		// although we can have a class map to multiple abbreviations for
		// encode (only the last registered is used).
		$key = self::ABBREV_PREFIX . $name;
		$existing = $this->abbrevToHintMap[$key] ?? null;
		if (
			$existing !== null &&
			!$abbrev->isSameAs( $existing )
		) {
			throw new InvalidArgumentException(
				"conflicting abbreviation for {$name}: {$existing->hint} != {$abbrev->hint}"
			);
		}
		$this->abbrevToHintMap[$key] = $abbrev;
		// Only abbreviations for bare class names (not hints) are serialized.
		if ( is_string( $hint ) ) {
			$existing = $this->classToAbbrevMap[$hint] ?? null;
			if ( $existing !== null && !$abbrev->isSameAs( $existing ) ) {
				throw new InvalidArgumentException(
					"too many abbreviations for {$hint}: {$existing->name}, {$abbrev->name}"
				);
			}
			$this->classToAbbrevMap[$hint] = $abbrev;
		}
		return $abbrev;
	}

	/**
	 * Return an abbreviation registered for the given abbrevation name.
	 */
	public function getAbbrev( string $name ): ?Abbrev {
		$key = self::ABBREV_PREFIX . $name;
		return $this->abbrevToHintMap[$key] ?? null;
	}

	/**
	 * Recursively converts a given object to an associative array
	 * which can be json-encoded.  (When embedding an object into
	 * another context it is sometimes useful to have the array
	 * representation rather than the string JSON form of the array;
	 * this can also be useful if you want to pretty-print the result,
	 * etc.)  While converting $value the JsonCodec delegates to the
	 * appropriate JsonClassCodecs of any classes which implement
	 * JsonCodecable.
	 *
	 * If a $classHint is provided and matches the type of the value,
	 * then type information will not be included in the generated JSON;
	 * otherwise an appropriate class name will be added to the JSON to
	 * guide deserialization.
	 *
	 * @param mixed|null $value
	 * @param class-string|Hint|Abbrev|null $classHint An optional hint to
	 *   the type of the encoded object.  If this is provided and matches
	 *   the type of $value, then explicit type information will be omitted
	 *   from the generated JSON, which saves some space.
	 * @return mixed|null
	 */
	public function toJsonArray( $value, $classHint = null ) {
		$is_complex = false;
		$className = 'array';
		$codec = null;

		// Process class hints
		$arrayClassHint = null;
		$forceBraces = null;
		$allowInherited = false;
		if ( $classHint instanceof Abbrev ) {
			$classHint = $classHint->hint;
		}
		while ( $classHint instanceof Hint ) {
			if ( $classHint->modifier === HintType::USE_SQUARE ) {
				// Allow list-like serializations to use []
				$classHint = $classHint->parent;
				$forceBraces = false;
			} elseif ( $classHint->modifier === HintType::ALLOW_OBJECT ) {
				// Force empty arrays to serialize as {}
				$classHint = $classHint->parent;
				$forceBraces = true;
			} elseif ( $classHint->modifier === HintType::LIST ) {
				// Array whose values are the hinted type
				$arrayClassHint = $classHint->parent;
				$classHint = 'array';
			} elseif ( $classHint->modifier === HintType::STDCLASS ) {
				// stdClass whose values are the hinted type
				$arrayClassHint = $classHint->parent;
				$classHint = stdClass::class;
			} elseif ( $classHint->modifier === HintType::INHERITED ) {
				// Allow the hint to match subclasses of the hinted class
				$classHint = $classHint->parent;
				$allowInherited = true;
			} elseif ( $classHint->modifier === HintType::ONLY_FOR_DECODE ) {
				// Don't use this hint for serialization.
				$classHint = null;
			} elseif ( $classHint->modifier === HintType::DEFAULT ) {
				// No-op, included for completeness
				$classHint = $classHint->parent;
			} else {
				throw new InvalidArgumentException( 'bad hint modifier: ' . $classHint->modifier->name );
			}
		}
		if ( is_object( $value ) ) {
			$className = get_class( $value );
			$codec = $this->codecFor( $className );
			if ( $codec !== null ) {
				$value = $codec->toJsonArray( $value );
				$is_complex = true;
			}
			// Tweak the codec used for class hints if $allowInherited is true
			// in order to match the codec we would use for deserialization.
			if (
				$allowInherited &&
				$classHint !== null &&
				is_a( $className, $classHint, true ) &&
				// extra comparison to let phan know $classHint is not 'array'
				$classHint !== 'array'
			) {
				$className = $classHint;
				$codec = $this->codecFor( $className );
			}
		} elseif (
			is_array( $value ) && $this->isArrayMarked( $value )
		) {
			$is_complex = true;
		}
		if ( is_array( $value ) ) {
			// Recursively convert array values to serializable form
			foreach ( $value as $key => &$v ) {
				if ( is_object( $v ) || is_array( $v ) ) {
					$propClassHint = $arrayClassHint;
					$propClassHint ??= ( $codec === null ? null :
						$codec->jsonClassHintFor( $className, (string)$key )
					);
					$v = $this->toJsonArray( $v, $propClassHint );
					if (
						$propClassHint !== null ||
						$this->isArrayMarked( $v )
					) {
						// an array which contains complex components is
						// itself complex.
						$is_complex = true;
					}
				}
			}
			// Ok, now mark the array, being careful to transfer away
			// any fields with the same names as our markers.
			if ( $is_complex || $classHint !== null ) {
				if (
					$forceBraces === null &&
					$className !== 'array' &&
					array_is_list( $value )
				) {
					// Include the type annotation (by clearing the
					// hint) if $forceBraces isn't false and it is
					// necessary to break up a list.  This ensures that
					// all objects have a JSON encoding in the `{...}`
					// style, even if they happen to have all-numeric
					// keys.
					$classHint = null;
				}
				// Even if $className === $classHint we may need to record this
				// array as "complex" (ie, requires recursion to process
				// individual values during deserialization)
				$this->markArray(
					$value, $className, $classHint
				);
				if ( $forceBraces === true && array_is_list( $value ) ) {
					// It is somewhat surprising for ::toJsonArray() to return
					// an object (rather than an array), but allow this case
					// if the class hint expressly asked for it.
					$value = (object)$value;
				}
			}
		} elseif ( !is_scalar( $value ) && $value !== null ) {
			throw new InvalidArgumentException(
				'Unable to serialize JSON: ' . get_debug_type( $value )
			);
		}
		return $value;
	}

	/**
	 * Recursively converts an associative array (or scalar) to an
	 * object value (or scalar).  While converting this value JsonCodec
	 * delegates to the appropriate JsonClassCodecs of any classes which
	 * implement JsonCodecable.
	 *
	 * For objects encoded using implicit class information, a "class hint"
	 * can be provided to guide deserialization; this is unnecessary for
	 * objects serialized with explicit classes.
	 *
	 * @param mixed|null $json
	 * @param class-string|Hint|Abbrev|null $classHint An optional hint to
	 *   the type of the encoded object.  In the absence of explicit
	 *   type information in the JSON, this will be used as the type of
	 *   the created object.
	 * @return mixed|null
	 */
	public function newFromJsonArray( $json, $classHint = null ) {
		if ( $json instanceof stdClass ) {
			// We *shouldn't* be given an object... but we might.
			$json = (array)$json;
		}

		// Process class hints
		$arrayClassHint = null;
		if ( $classHint instanceof Abbrev ) {
			$classHint = $classHint->hint;
		}
		while ( $classHint instanceof Hint ) {
			if ( $classHint->modifier === HintType::LIST ) {
				$arrayClassHint = $classHint->parent;
				$classHint = 'array';
			} elseif ( $classHint->modifier === HintType::STDCLASS ) {
				$arrayClassHint = $classHint->parent;
				$classHint = stdClass::class;
			} else {
				$classHint = $classHint->parent;
			}
		}

		// Is this an array containing a complex value?
		if (
			is_array( $json ) && (
				$this->isArrayMarked( $json ) || $classHint !== null
			)
		) {
			// Read out our metadata
			$className = $this->unmarkArray( $json, $classHint );
			// Create appropriate codec
			$codec = null;
			if ( $className !== 'array' ) {
				$codec = $this->codecFor( $className );
				if ( $codec === null ) {
					throw new InvalidArgumentException(
						"Unable to deserialize JSON for $className"
					);
				}
			}
			// Recursively unserialize the array contents.
			$unserialized = [];
			foreach ( $json as $key => $value ) {
				$propClassHint = $arrayClassHint;
				$propClassHint ??= ( $codec === null ? null :
					// phan can't tell that $codec is null when $className is 'array'
					// @phan-suppress-next-line PhanUndeclaredClassReference
					$codec->jsonClassHintFor( $className, (string)$key )
				);
				if ( $value instanceof stdClass ) {
					// Again, we *shouldn't* be given an object... but we might.
					$value = (array)$value;
				}
				if (
					is_array( $value ) && (
						$this->isArrayMarked( $value ) || $propClassHint !== null
					)
				) {
					$unserialized[$key] = $this->newFromJsonArray( $value, $propClassHint );
				} else {
					$unserialized[$key] = $value;
				}
			}
			// Use a JsonCodec to create the object instance if appropriate.
			if ( $className === 'array' ) {
				$json = $unserialized;
			} else {
				$json = $codec->newFromJsonArray( $className, $unserialized );
			}
		}
		return $json;
	}

	// Functions to mark/unmark arrays and record a class name using a
	// single reserved field, named by self::TYPE_ANNOTATION.  A
	// subclass can provide alternate implementations of these methods
	// if it wants to use a different reserved field or else wishes to
	// reserve more fields/encode certain types more compactly/flag
	// certain types of values.  For example: a subclass could choose
	// to discard all hints in `markArray` in order to explicitly mark
	// all types in preparation for a format change; or all values of
	// type DocumentFragment might get a marker flag added so they can
	// be identified without knowledge of the class hint; or perhaps a
	// separate schema can be used to record class names more
	// compactly.

	/**
	 * Determine if the given value is "marked"; that is, either
	 * represents a object type encoded using a JsonClassCodec or else
	 * is an array which contains values (or contains arrays
	 * containing values, etc) which are object types. The values of
	 * unmarked arrays are not decoded, in order to speed up the
	 * decoding process.  Arrays may also be marked even if they do
	 * not represent object types (or an array recursively containing
	 * them) if they contain keys that need to be escaped ("false
	 * marks"); as such this method is called both on the raw results
	 * of JsonClassCodec (to check for "false marks") as well as on
	 * encoded arrays (to find "true marks").
	 *
	 * Arrays do not have to be marked if the decoder has a class hint.
	 *
	 * @param array $value An array result from `JsonClassCodec::toJsonArray()`,
	 *  or an array result from `::markArray()`
	 * @return bool Whether the $value is marked
	 */
	protected function isArrayMarked( array $value ): bool {
		return array_key_exists( self::TYPE_ANNOTATION, $value );
	}

	/**
	 * Record a mark in the array, reversibly.
	 *
	 * The mark should record the class name, if it is different from
	 * the class hint.  The result does not need to trigger
	 * `::isArrayMarked` if there is an accurate class hint present,
	 * but otherwise the result should register as marked.  The
	 * provided value may be a "complex" array (one that recursively
	 * contains encoded object) or an array with a "false mark"; in
	 * both cases the provided $className will be `array`.
	 *
	 * @param array &$value An array result from `JsonClassCodec::toJsonArray()`
	 *   or a "complex" array
	 * @param 'array'|class-string $className The name of the class encoded
	 *   by the codec, or else `array` if $value is a "complex" array or a
	 *   "false mark"
	 * @param 'array'|class-string|null $classHint The class name provided as
	 *   a hint to the encoder, and which will be in turn provided as a hint
	 *   to the decoder, or `null` if no hint was provided.  The class hint
	 *   will be `array` when the array is a homogeneous list of objects.
	 */
	protected function markArray( array &$value, string $className, ?string $classHint ): void {
		// We're going to use an array key, but first we have to see whether it
		// was already present in the array we've been given, in which case
		// we need to escape it (by hoisting into a child array).
		if ( array_key_exists( self::TYPE_ANNOTATION, $value ) ) {
			if ( !self::classEquals( $className, $classHint ) ) {
				$abbrev = $this->classToAbbrevMap[$className] ?? null;
				$type = ( $abbrev === null ) ? $className :
					  ( self::ABBREV_PREFIX . $abbrev->name );
				$value[self::TYPE_ANNOTATION] = [ $value[self::TYPE_ANNOTATION], $type ];
			} else {
				// Omit $className since it matches the $classHint, but we still
				// need to escape the field to make it clear it was marked.
				// (If the class hint hadn't matched, the proper class name
				// would be here in an array, and we need to distinguish that
				// case from the case where the "actual value" is an array.)
				$value[self::TYPE_ANNOTATION] = [ $value[self::TYPE_ANNOTATION] ];
			}
		} elseif (
			!self::classEquals( $className, $classHint )
		) {
			// Include the type annotation if it doesn't match the hint
			$abbrev = $this->classToAbbrevMap[$className] ?? null;
			$type = ( $abbrev === null ) ? $className :
				  ( self::ABBREV_PREFIX . $abbrev->name );
			$value[self::TYPE_ANNOTATION] = $type;
		}
	}

	/**
	 * Remove a mark from an encoded array, and return an
	 * encoded class name if present.
	 *
	 * The provided array may not trigger `::isArrayMarked` is there
	 * was a class hint provided.
	 *
	 * If the provided array had a "false mark" or recursively
	 * contained objects, the returned class name should be 'array'.
	 *
	 * @param array &$value An encoded array
	 * @param 'array'|class-string|null $classHint The class name provided as a hint to
	 *  the decoder, which was previously provided as a hint to the encoder,
	 *   or `null` if no hint was provided.
	 * @return 'array'|class-string The class name to be used for decoding, or
	 *  'array' if the value was a "complex" or "false mark" array.
	 */
	protected function unmarkArray( array &$value, ?string $classHint ): string {
		$abbrevOrClass = $value[self::TYPE_ANNOTATION] ?? null;
		// Remove our marker and restore the previous state of the
		// json array (restoring a pre-existing field if needed)
		if ( is_array( $abbrevOrClass ) ) {
			[ $oldValue, $abbrevOrClass ] = array_pad( $abbrevOrClass, 2, null );
			$value[self::TYPE_ANNOTATION] = $oldValue;
		} else {
			unset( $value[self::TYPE_ANNOTATION] );
		}
		if ( str_starts_with( $abbrevOrClass ?? '', self::ABBREV_PREFIX ) ) {
			$abbrev = $this->abbrevToHintMap[$abbrevOrClass] ?? null;
			if ( $abbrev === null ) {
				throw new InvalidArgumentException(
					"Unknown abbreviation: $abbrevOrClass"
				);
			}
			$className = $abbrev->hint;
			while ( $className instanceof Hint && $className->modifier === HintType::ONLY_FOR_DECODE ) {
				$className = $className->parent;
			}
			if ( !is_string( $className ) ) {
				throw new InvalidArgumentException(
					"Abbreviation used to encode a hint, not a class"
				);
			}
		} else {
			$className = $abbrevOrClass ?? $classHint;
		}
		return $className;
	}

	/**
	 * Helper function to test two class strings for equality in the presence
	 * of class aliases.
	 *
	 * @param 'array'|class-string $class1
	 * @param 'array'|class-string|null $class2
	 * @return bool True if the arguments refer to the same class
	 */
	private static function classEquals( string $class1, ?string $class2 ): bool {
		if ( $class1 === $class2 ) {
			// Fast path
			return true;
		}
		if ( $class2 === null || $class1 === 'array' || $class2 === 'array' ) {
			return false;
		}
		if ( is_a( $class1, $class2, true ) && is_a( $class2, $class1, true ) ) {
			// Check aliases
			return true;
		}
		return false;
	}
}
