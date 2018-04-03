<?php

namespace Deserializers\Exceptions;

use Exception;

/**
 * A deserialization exception that is thrown when an expected array key is present, but it's value
 * is not in the expected format.
 *
 * @since 1.0
 *
 * @license GPL-2.0+
 * @author Jeroen De Dauw < jeroendedauw@gmail.com >
 * @author Thiemo Mättig
 */
class InvalidAttributeException extends DeserializationException {

	private $attributeName;
	private $attributeValue;

	/**
	 * @param string $attributeName
	 * @param mixed $attributeValue
	 * @param string $message
	 * @param Exception|null $previous
	 */
	public function __construct(
		$attributeName,
		$attributeValue,
		$message = '',
		Exception $previous = null
	) {
		$this->attributeName = $attributeName;
		$this->attributeValue = $attributeValue;

		if ( $message === '' ) {
			$message = 'Attribute "' . $attributeName . '"';

			if ( is_scalar( $attributeValue ) ) {
				$message .= ' with value "' . $attributeValue . '"';
			}

			$message .= ' is invalid';
		}

		parent::__construct( $message, $previous );
	}

	/**
	 * @return string
	 */
	public function getAttributeName() {
		return $this->attributeName;
	}

	/**
	 * @return string
	 */
	public function getAttributeValue() {
		return $this->attributeValue;
	}

}
