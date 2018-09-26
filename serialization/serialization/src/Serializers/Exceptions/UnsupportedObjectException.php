<?php

namespace Serializers\Exceptions;

use Exception;

/**
 * @since 1.0
 *
 * @license GPL-2.0+
 * @author Jeroen De Dauw < jeroendedauw@gmail.com >
 */
class UnsupportedObjectException extends SerializationException {

	private $unsupportedObject;

	/**
	 * @param mixed $unsupportedObject
	 * @param string $message
	 * @param Exception|null $previous
	 */
	public function __construct( $unsupportedObject, $message = '', Exception $previous = null ) {
		$this->unsupportedObject = $unsupportedObject;

		parent::__construct( $message, $previous );
	}

	/**
	 * @return mixed
	 */
	public function getUnsupportedObject() {
		return $this->unsupportedObject;
	}

}
