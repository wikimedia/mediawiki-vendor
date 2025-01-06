<?php

namespace Deserializers\Exceptions;

use Throwable;

/**
 * A deserialization exception that is thrown when an expected array key is not present.
 *
 * @since 1.0
 *
 * @license GPL-2.0-or-later
 * @author Jeroen De Dauw < jeroendedauw@gmail.com >
 * @author Thiemo Kreuz
 */
class MissingAttributeException extends DeserializationException {

	private $attributeName;

	/**
	 * @param string $attributeName
	 * @param string $message
	 * @param Throwable|null $previous
	 */
	public function __construct( $attributeName, $message = '', ?Throwable $previous = null ) {
		$this->attributeName = $attributeName;

		if ( $message === '' ) {
			$message = 'Attribute "' . $attributeName . '" is missing';
		}

		parent::__construct( $message, $previous );
	}

	/**
	 * @return string
	 */
	public function getAttributeName() {
		return $this->attributeName;
	}

}
