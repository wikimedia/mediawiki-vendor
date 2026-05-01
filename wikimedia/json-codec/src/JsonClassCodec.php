<?php
declare( strict_types=1 );

/**
 * @license GPL-2.0-or-later
 * @file
 */

namespace Wikimedia\JsonCodec;

/**
 * Classes implementing this interface support round-trip JSON
 * serialization/deserialization for certain class types.
 * They may maintain state and/or consult service objects which
 * are stored in the codec object.
 *
 * @template T
 */
interface JsonClassCodec {

	/**
	 * Returns a JSON array representing the contents of the given object, that
	 * can be deserialized with the corresponding newFromJsonArray() method.
	 *
	 * The returned array can contain other JsonCodecables as values;
	 * the JsonCodec class will take care of encoding values in the array
	 * as needed, as well as annotating the returned array with the class
	 * information needed to locate the correct ::newFromJsonArray()
	 * method during deserialization.
	 *
	 * Only objects of the types registered to this JsonClassCodec will be
	 * provided to this method.
	 *
	 * @param T $obj An object of the type handled by this JsonClassCodec
	 * @return array A Json representation of the object.
	 */
	public function toJsonArray( $obj ): array;

	/**
	 * Creates a new instance of the given class and initializes it from the
	 * $json array.
	 * @param class-string<T> $className
	 * @param array $json
	 * @return T
	 */
	public function newFromJsonArray( string $className, array $json );

	/**
	 * Return an optional type hint for the given array key in the result of
	 * ::toJsonArray() / input to ::newFromJsonArray.  If a class name is
	 * returned here and it matches the runtime type of the value of that
	 * array key, then type information will be omitted from the generated
	 * JSON which can save space.  The class name can be suffixed with `[]`
	 * to indicate an array or list containing objects of the given class
	 * name.
	 *
	 * @param class-string<T> $className The class we're looking for a hint for
	 * @param string $keyName The name of the array key we'd like a hint on
	 * @return class-string|string|Hint|null A class string, Hint or null.
	 *   For backward compatibility, a class string suffixed with `[]` can
	 *   also be returned, but that is deprecated.
	 */
	public function jsonClassHintFor( string $className, string $keyName );
}
