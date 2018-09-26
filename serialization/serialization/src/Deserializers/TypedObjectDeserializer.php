<?php

namespace Deserializers;

use Deserializers\Exceptions\InvalidAttributeException;
use Deserializers\Exceptions\MissingAttributeException;
use Deserializers\Exceptions\MissingTypeException;
use Deserializers\Exceptions\UnsupportedTypeException;

/**
 * @since 1.0
 *
 * @license GPL-2.0+
 * @author Jeroen De Dauw < jeroendedauw@gmail.com >
 */
abstract class TypedObjectDeserializer implements DispatchableDeserializer {

	/**
	 * @var string
	 */
	private $objectType;

	/**
	 * @var string
	 */
	private $typeKey;

	/**
	 * @param string $objectType
	 * @param string $typeKey
	 */
	public function __construct( $objectType, $typeKey = 'objectType' ) {
		$this->objectType = $objectType;
		$this->typeKey = $typeKey;
	}

	protected function assertCanDeserialize( $serialization ) {
		if ( !$this->hasObjectType( $serialization ) ) {
			throw new MissingTypeException();
		}

		if ( !$this->hasCorrectObjectType( $serialization ) ) {
			throw new UnsupportedTypeException( $serialization[$this->typeKey] );
		}
	}

	public function isDeserializerFor( $serialization ) {
		return $this->hasObjectType( $serialization ) && $this->hasCorrectObjectType( $serialization );
	}

	private function hasCorrectObjectType( array $serialization ) {
		return $serialization[$this->typeKey] === $this->objectType;
	}

	private function hasObjectType( $serialization ) {
		return is_array( $serialization )
			&& array_key_exists( $this->typeKey, $serialization );
	}

	/**
	 * @deprecated since 4.0, just do your own "if ( array_key_exists( … ) )" or
	 *  "if ( isset( … ) )" instead
	 *
	 * @param array $array
	 * @param string $attributeName
	 */
	protected function requireAttribute( array $array, $attributeName ) {
		if ( !array_key_exists( $attributeName, $array ) ) {
			throw new MissingAttributeException(
				$attributeName
			);
		}
	}

	/**
	 * @deprecated since 4.0, just do your own "if ( is_array( … ) )" instead
	 *
	 * @param array $array
	 * @param string $attributeName
	 */
	protected function assertAttributeIsArray( array $array, $attributeName ) {
		$this->assertAttributeInternalType( $array, $attributeName, 'array' );
	}

	/**
	 * @deprecated since 4.0, just do your own "if ( is_string( … ) )" and such instead
	 *
	 * @param array $array
	 * @param string $attributeName
	 * @param string $internalType
	 */
	protected function assertAttributeInternalType( array $array, $attributeName, $internalType ) {
		if ( gettype( $array[$attributeName] ) !== $internalType ) {
			throw new InvalidAttributeException(
				$attributeName,
				$array[$attributeName],
				"The internal type of attribute '$attributeName'  needs to be '$internalType'"
			);
		}
	}

}
