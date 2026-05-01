<?php
declare( strict_types=1 );

/**
 * @license GPL-2.0-or-later
 * @file
 * @ingroup Json
 */

namespace Wikimedia\JsonCodec;

/**
 * This is a simple class codec which proxies to methods on the object for
 * serialization and a static method on the class for deserialization.
 * It is intended for use as a singleton helper to JsonCodecableTrait.
 *
 * @implements JsonClassCodec<JsonCodecableTrait>
 */
class JsonStaticClassCodec implements JsonClassCodec {

	/**
	 * Returns a JSON array representing the contents of the given object, that
	 * can be deserialized with the corresponding newFromJsonArray() method,
	 * using a ::toJsonArray() method on the object itself.
	 *
	 * @param object $obj An object of the type handled by this JsonClassCodec
	 * @return array A Json representation of the object.
	 * @inheritDoc
	 * @see JsonCodecableTrait
	 */
	public function toJsonArray( $obj ): array {
		// Proxy to a method on the object itself.
		// @see JsonCodecableTrait
		return $obj->toJsonArray();
	}

	/**
	 * Creates a new instance of the given class and initializes it from the
	 * $json array, using a static method on $className.
	 *
	 * @inheritDoc
	 * @see JsonCodecableTrait
	 */
	public function newFromJsonArray( string $className, array $json ) {
		// Proxy to a static method on the class.
		// @see JsonCodecableTrait
		return $className::newFromJsonArray( $json );
	}

	/**
	 * Return an optional type hint for the given array key in the result of
	 * ::toJsonArray() / input to ::newFromJsonArray.  If a class name is
	 * returned here and it matches the runtime type of the value of that
	 * array key, then type information will be omitted from the generated
	 * JSON which can save space.  The class name can be suffixed with `[]`
	 * to indicate an array or list containing objects of the given class
	 * name.
	 *
	 * @param class-string<T> $className
	 * @param string $keyName
	 * @return class-string|string|Hint|null A class string, Hint or null.
	 *   For backward compatibility, a class string suffixed with `[]` can
	 *   also be returned, but that is deprecated.
	 */
	public function jsonClassHintFor( string $className, string $keyName ) {
		// Proxy to a static method on the class.
		// @see JsonCodecableTrait
		return $className::jsonClassHintFor( $keyName );
	}

	/**
	 * Return a singleton instance of this class codec.
	 * @return JsonStaticClassCodec a singleton instance of this class
	 */
	public static function getInstance(): JsonStaticClassCodec {
		static $instance = null;
		if ( $instance == null ) {
			$instance = new JsonStaticClassCodec();
		}
		return $instance;
	}
}
