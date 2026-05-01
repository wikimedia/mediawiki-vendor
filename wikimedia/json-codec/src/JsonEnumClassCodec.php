<?php
declare( strict_types=1 );

/**
 * @license GPL-2.0-or-later
 * @file
 * @ingroup Json
 */

namespace Wikimedia\JsonCodec;

use BackedEnum;
use UnitEnum;

/**
 * This is a simple class codec for instances of Enum types.
 * It is intended for use as a singleton helper to JsonCodec.
 *
 * @implements JsonClassCodec<UnitEnum>
 */
class JsonEnumClassCodec implements JsonClassCodec {

	/**
	 * Returns a JSON array representing the contents of the given object, that
	 * can be deserialized with the corresponding newFromJsonArray() method,
	 * using a ::toJsonArray() method on the object itself.
	 *
	 * @param UnitEnum $obj An object of the type handled by this JsonClassCodec
	 * @return array A Json representation of the object.
	 */
	public function toJsonArray( $obj ): array {
		// @var Enum $obj
		if ( $obj instanceof BackedEnum ) {
			return [ 'value' => $obj->value ];
		}
		return [ 'name' => $obj->name ];
	}

	/**
	 * Returns the appropriate case of the enum
	 * @param class-string<UnitEnum> $className
	 * @param array $json
	 * @return UnitEnum
	 */
	public function newFromJsonArray( string $className, array $json ) {
		if ( is_a( $className, BackedEnum::class, true ) ) {
			return $className::from( $json['value'] );
		}
		$name = $json['name'];
		// In PHP >= 8.3 this is just `$className::{$name}`
		// https://wiki.php.net/rfc/dynamic_class_constant_fetch
		return constant( $className . "::{$name}" );
	}

	/**
	 * No type hint for this enum.
	 *
	 * @param class-string<UnitEnum> $className
	 * @param string $keyName
	 * @return null Returns null.
	 */
	public function jsonClassHintFor( string $className, string $keyName ) {
		return null;
	}

	/**
	 * Return a singleton instance of this class codec.
	 * @return self a singleton instance of this class
	 */
	public static function getInstance(): self {
		static $instance = null;
		if ( $instance == null ) {
			$instance = new JsonEnumClassCodec();
		}
		return $instance;
	}
}
