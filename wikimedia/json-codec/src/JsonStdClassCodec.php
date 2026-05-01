<?php
declare( strict_types=1 );

/**
 * @license GPL-2.0-or-later
 * @file
 */

namespace Wikimedia\JsonCodec;

use stdClass;

/**
 * This is a simple class codec used for `stdClass` objects.
 *
 * @internal
 * @implements JsonClassCodec<stdClass>
 */
class JsonStdClassCodec implements JsonClassCodec {

	/**
	 * Returns a JSON array representing the contents of the given object, that
	 * can be deserialized with the corresponding newFromJsonArray() method,
	 * using a ::toJsonArray() method on the object itself.
	 *
	 * @param stdClass $obj An object of the type handled by this JsonClassCodec
	 * @return array A Json representation of the object.
	 * @see JsonCodecableTrait
	 */
	public function toJsonArray( $obj ): array {
		return (array)$obj;
	}

	/**
	 * Creates a new instance of the given class and initializes it from the
	 * $json array, using a static method on $className.
	 *
	 * @param class-string<stdClass> $className
	 * @param array $json
	 * @return stdClass
	 */
	public function newFromJsonArray( string $className, array $json ) {
		return (object)$json;
	}

	/**
	 * Returns null, to indicate no type hint for any properties in the
	 * `stdClass` value being encoded.
	 *
	 * @param class-string<T> $className
	 * @param string $keyName
	 * @return null Always returns null
	 */
	public function jsonClassHintFor( string $className, string $keyName ) {
		return null;
	}

	/**
	 * Return a singleton instance of this stdClass codec.
	 * @return JsonStdClassCodec a singleton instance of this class
	 */
	public static function getInstance(): JsonStdClassCodec {
		static $instance = null;
		if ( $instance == null ) {
			$instance = new JsonStdClassCodec();
		}
		return $instance;
	}
}
