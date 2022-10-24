<?php

namespace DataValues\Deserializers;

use DataValues\DataValue;
use Deserializers\DispatchableDeserializer;
use Deserializers\Exceptions\DeserializationException;
use Deserializers\Exceptions\MissingAttributeException;
use Deserializers\Exceptions\MissingTypeException;
use Deserializers\Exceptions\UnsupportedTypeException;
use InvalidArgumentException;
use RuntimeException;

/**
 * @since 0.1
 *
 * @license GPL-2.0-or-later
 * @author Jeroen De Dauw < jeroendedauw@gmail.com >
 * @author Thiemo Kreuz
 */
class DataValueDeserializer implements DispatchableDeserializer {

	public const TYPE_KEY = 'type';
	public const VALUE_KEY = 'value';

	/**
	 * @var array Associative array mapping data type IDs to either callables returning new
	 *  DataValue objects, or full qualified DataValue class names.
	 */
	private $builders;

	/**
	 * @since 0.1, callables are supported since 1.1
	 *
	 * @param array $builders Associative array mapping data type IDs to either callables, or full
	 *  qualified class names. Callables must accept a single value as specified by the
	 *  corresponding DataValue::getArrayValue, and return a new DataValue object. DataValue classes
	 *  given via class name must implement a static newFromArray method doing the same.
	 */
	public function __construct( array $builders = [] ) {
		$this->assertAreDataValueClasses( $builders );
		$this->builders = $builders;
	}

	private function assertAreDataValueClasses( array $builders ) {
		foreach ( $builders as $type => $builder ) {
			if ( !is_string( $type )
				|| ( !is_callable( $builder ) && !$this->isDataValueClass( $builder ) )
			) {
				$message = '$builders must map data types to callables or class names';
				if ( is_string( $builder ) ) {
					$message .= ". '$builder' is not a DataValue class.";
				}
				throw new InvalidArgumentException( $message );
			}
		}
	}

	private function isDataValueClass( $class ) {
		return is_string( $class )
			&& class_exists( $class )
			&& in_array( DataValue::class, class_implements( $class ) );
	}

	/**
	 * @see DispatchableDeserializer::isDeserializerFor
	 *
	 * @param mixed $serialization
	 *
	 * @return bool
	 */
	public function isDeserializerFor( $serialization ) {
		try {
			$this->assertCanDeserialize( $serialization );
			return true;
		} catch ( RuntimeException $ex ) {
			return false;
		}
	}

	/**
	 * @see Deserializer::deserialize
	 *
	 * @param array $serialization
	 *
	 * @throws DeserializationException
	 * @return DataValue
	 */
	public function deserialize( $serialization ) {
		$this->assertCanDeserialize( $serialization );
		return $this->getDeserialization( $serialization );
	}

	private function assertCanDeserialize( $serialization ) {
		if ( !is_array( $serialization ) || !array_key_exists( self::TYPE_KEY, $serialization ) ) {
			throw new MissingTypeException( 'Not an array or missing the key "' . self::TYPE_KEY . '"' );
		}

		if ( !array_key_exists( self::VALUE_KEY, $serialization ) ) {
			throw new MissingAttributeException( self::VALUE_KEY );
		}

		$type = $serialization[self::TYPE_KEY];
		if ( !array_key_exists( $type, $this->builders ) ) {
			throw new UnsupportedTypeException( $type );
		}
	}

	/**
	 * @param array $serialization
	 *
	 * @throws DeserializationException
	 * @return DataValue
	 */
	private function getDeserialization( array $serialization ) {
		$type = $serialization[self::TYPE_KEY];
		$value = $serialization[self::VALUE_KEY];
		$builder = $this->builders[$type];

		try {
			if ( is_callable( $builder ) ) {
				return $builder( $value );
			}

			/** @var DataValue $builder */
			return $builder::newFromArray( $value );
		} catch ( InvalidArgumentException $ex ) {
			throw new DeserializationException( $ex->getMessage(), $ex );
		}
	}

}
