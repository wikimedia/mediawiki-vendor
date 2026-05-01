<?php
declare( strict_types=1 );

/**
 * @license GPL-2.0-or-later
 * @file
 */

namespace Wikimedia\JsonCodec;

use Psr\Container\ContainerInterface;
use stdClass;

/**
 * The JsonCodecableTrait aids in the implementation of stateless codecs.
 * The class using the trait need only define stateless ::toJsonArray() and
 * ::newFromJsonArray() methods.
 *
 * The class using the trait should also implement JsonCodecable
 * (https://wiki.php.net/rfc/traits-with-interfaces may allow the trait
 * to do this directly in a future PHP version).
 */
trait JsonCodecableTrait {

	/**
	 * Implements JsonCodecable by providing an implementation of
	 * ::jsonClassCodec() which does not use the provided $serviceContainer
	 * nor does it maintain any state; it just calls the ::toJsonArray()
	 * and ::newFromJsonArray() methods of this instance.
	 * @param JsonCodecInterface $codec
	 * @param ContainerInterface $serviceContainer
	 * @return JsonClassCodec
	 */
	public static function jsonClassCodec(
		JsonCodecInterface $codec, ContainerInterface $serviceContainer
	): JsonClassCodec {
		// In advanced JIT implementations optimization of the method
		// dispatch in this class can be performed if we keep the
		// codecs for each class separate.  However, for simplicity
		// (and to reduce memory usage) we'll use a singleton object
		// shared with all classes which use this trait.
		return JsonStaticClassCodec::getInstance();
	}

	/**
	 * Return an associative array representing the contents of this object,
	 * which can be passed to ::newFromJsonArray() to deserialize it.
	 * @return array
	 */
	abstract public function toJsonArray(): array;

	/**
	 * Return an instance of this object representing the deserialization
	 * from the array passed in $json.
	 * @param array $json
	 * @return stdClass
	 */
	abstract public static function newFromJsonArray( array $json );

	/**
	 * Return an optional type hint for the given array key in the result of
	 * ::toJsonArray() / input to ::newFromJsonArray.  If a class name is
	 * returned here and it matches the runtime type of the value of that
	 * array key, then type information will be omitted from the generated
	 * JSON which can save space.  The class name can be suffixed with `[]`
	 * to indicate an array or list containing objects of the given class
	 * name.
	 *
	 * Default implementation of ::jsonClassHintFor() provides no hints.
	 * Implementer can override.
	 *
	 * @param string $keyName
	 * @return class-string|string|Hint|null A class string, Hint, or null.
	 *   For backward compatibility, a class string suffixed with `[]` can
	 *   also be returned, but that is deprecated.
	 */
	public static function jsonClassHintFor( string $keyName ) {
		return null;
	}
}
