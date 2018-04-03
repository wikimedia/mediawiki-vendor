<?php

namespace Deserializers\Exceptions;

use Exception;

/**
 * Indicates the objectType specified in the serialization is not supported by a deserializer.
 *
 * @since 1.0
 *
 * @license GPL-2.0+
 * @author Jeroen De Dauw < jeroendedauw@gmail.com >
 * @author Thiemo Mättig
 */
class UnsupportedTypeException extends DeserializationException {

	private $type;

	/**
	 * @param mixed $type
	 * @param string $message
	 * @param Exception|null $previous
	 */
	public function __construct( $type, $message = '', Exception $previous = null ) {
		$this->type = $type;

		if ( $message === '' && is_scalar( $type ) ) {
			$message = 'Type "' . $type . '" is unsupported';
		}

		parent::__construct( $message, $previous );
	}

	/**
	 * @return mixed
	 */
	public function getUnsupportedType() {
		return $this->type;
	}

}
