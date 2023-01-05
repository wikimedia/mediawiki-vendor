<?php namespace SmashPig\Core\DataStores;

use SmashPig\Core\Logging\Logger;

/**
 * Base class providing generic serialization/deserialization capabilities.
 */
abstract class JsonSerializableObject {
	/** @var array List of public/protected properties that should not be serialized. */
	protected $propertiesExcludedFromExport = [];

	/**
	 * Default constructor.
	 */
	public function __construct() {
	}

	/**
	 * Backend factory function called from fromJson(). Should be customized if
	 * merely filling properties and then calling __wakeup() is not sufficient for
	 * reserializing the object. Should also be customized if the default constructor
	 * has required arguments.
	 *
	 * @param string $className Name of the class to instantiate
	 * @param array $properties Stored properties from the serialized object
	 * (Keys = property names, Values = property values)
	 *
	 * @return JsonSerializableObject Object ready for __wakeup().
	 */
	protected static function serializedConstructor( $className, $properties = [] ) {
		$obj = new $className();
		foreach ( $properties as $propName => $propValue ) {
			$obj->$propName = $propValue;
		}

		return $obj;
	}

	/**
	 * Serialize an object to a JSON string.
	 *
	 * This function will call __sleep() on the object and attempt to save all requested
	 * properties listed (IE: all non-static properties that are not listed in the
	 * propertiesExcludedFromExport list).
	 *
	 * Object properties may be serialized only if they inherit from JsonSerializableObject.
	 *
	 * If $resumeUse is specified 'true' then __wakeup() will be called at the termination of
	 * this function. As this is the case, it is not safe to continue to use the object after
	 * serialization if $resumeUse = false.
	 *
	 * @param bool $resumeUse Set to false if this object reference will not be further used this
	 *                        session post serialization.
	 *
	 * @return string JSON string representing the object.
	 */
	public function toJson( $resumeUse = true ) {
		$this->__sleep();

		$properties = [];

		foreach ( get_object_vars( $this ) as $propName => $propValue ) {
			if ( !in_array( $propName, $this->propertiesExcludedFromExport ) ) {
				if ( is_object( $propValue ) ) {
					if ( $propValue instanceof JsonSerializableObject ) {
						$properties[ $propName ] = $propValue->toJson( $resumeUse );
					} else {
						$className = get_class();
						Logger::warning( "Object contained in property {$className}->$propName is not instance of JsonSerializableObject." );
					}
				} else {
					$properties[ $propName ] = $propValue;
				}
			}
		}

		if ( $resumeUse ) {
			$this->__wakeup();
		}

		return json_encode( $properties );
	}

	/**
	 * Public interface into a factory for creating JsonSerializableObject objects from
	 * JSON strings. Inverse of toJson().
	 *
	 * Interested classes may override serializedConstructor() to get custom reconstruction
	 * behaviour. This is REQUIRED when __construct() is overriden with required parameters.
	 *
	 * @param string $className The name of the class to construct
	 * @param string $jsonStr JSON string to recompose the object from.
	 *
	 * @throws DataSerializationException
	 * @return JsonSerializableObject
	 */
	public static function fromJson( $className, $jsonStr ) {
		$properties = json_decode( $jsonStr );

		if ( is_a( $properties, 'stdClass' ) ) {
			$obj = static::serializedConstructor( $className, $properties );
			$obj->__wakeup();
			return $obj;
		} elseif ( $properties === null ) {
			throw new DataSerializationException( "Object had null body. Can not deserialize." );
		} elseif ( $properties === false ) {
			throw new DataSerializationException( "Object has malformed JSON. Can not deserialize." );
		}
	}

	/**
	 * For those instances where you must instantiate a serialized object from a string.
	 *
	 * @param string $className Fully qualified class name. Must inherit from JsonSerializableObject
	 * @param string $jsonStr JSON string to populate the object with.
	 *
	 * @return JsonSerializableObject
	 * @throws DataSerializationException
	 */
	public static function fromJsonProxy( $className, $jsonStr ) {
		if ( is_callable( "$className::fromJson" ) ) {
			$classObj = call_user_func( "$className::fromJson", $className, $jsonStr );

			if ( !( $classObj instanceof JsonSerializableObject ) ) {
				throw new DataSerializationException(
					"Class '{$className}' has fromJson() but is not a JsonSerializableObject?"
				);
			}

		} else {
			throw new DataSerializationException(
				"Class '{$className}' does not implement fromJson()! Cannot deserialize data."
			);
		}

		return $classObj;
	}

	/**
	 * Function run before any serialization routine is run on the object. This includes
	 * the toJson() function.
	 */
	public function __sleep() {
	}

	/**
	 * Function run after any serialization routine is run on the object. This includes
	 * the fromJson() function.
	 */
	public function __wakeup() {
	}
}
