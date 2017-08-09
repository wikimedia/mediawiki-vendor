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
 * @license GPL-2.0+
 * @author Jeroen De Dauw < jeroendedauw@gmail.com >
 * @author Thiemo Mättig
 */
class DataValueDeserializer implements DispatchableDeserializer {

	const TYPE_KEY = 'type';
	const VALUE_KEY = 'value';

	/**
	 * @var array Associative array mapping data type IDs to either callables returning new
	 *  DataValue objects, or full qualified DataValue class names.
	 */
	private $builders;

	private $serialization;

	/**
	 * @since 0.1, callables are supported since 1.1
	 *
	 * @param array $builders Associative array mapping data type IDs to either callables, or full
	 *  qualified class names. Callables must accept a single value as specified by the
	 *  corresponding DataValue::getArrayValue, and return a new DataValue object. DataValue classes
	 *  given via class name must implement a static newFromArray method doing the same.
	 */
	public function __construct( array $builders = array() ) {
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
		$this->serialization = $serialization;

		try {
			$this->assertCanDeserialize();
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
		$this->serialization = $serialization;

		$this->assertCanDeserialize();
		return $this->getDeserialization();
	}

	private function assertCanDeserialize() {
		$this->assertHasSupportedType();
		$this->assertHasValue();
	}

	private function assertHasSupportedType() {
		if ( !is_array( $this->serialization ) || !array_key_exists( self::TYPE_KEY, $this->serialization ) ) {
			throw new MissingTypeException();
		}

		if ( !$this->hasSupportedType() ) {
			throw new UnsupportedTypeException( $this->getType() );
		}
	}

	private function assertHasValue() {
		if ( !array_key_exists( self::VALUE_KEY, $this->serialization ) ) {
			throw new MissingAttributeException( self::VALUE_KEY );
		}
	}

	private function hasSupportedType() {
		return array_key_exists( $this->getType(), $this->builders );
	}

	private function getType() {
		return $this->serialization[self::TYPE_KEY];
	}

	/**
	 * @throws DeserializationException
	 * @return DataValue
	 */
	private function getDeserialization() {
		$builder = $this->builders[$this->getType()];

		try {
			if ( is_callable( $builder ) ) {
				return $builder( $this->getValue() );
			}

			/** @var DataValue $builder */
			return $builder::newFromArray( $this->getValue() );
		} catch ( InvalidArgumentException $ex ) {
			throw new DeserializationException( $ex->getMessage(), $ex );
		}
	}

	private function getValue() {
		return $this->serialization[self::VALUE_KEY];
	}

}
