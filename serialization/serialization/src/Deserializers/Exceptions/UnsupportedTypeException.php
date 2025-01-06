<?php

namespace Deserializers\Exceptions;

use Throwable;

/**
 * Indicates the objectType specified in the serialization is not supported by a deserializer.
 *
 * @since 1.0
 *
 * @license GPL-2.0-or-later
 * @author Jeroen De Dauw < jeroendedauw@gmail.com >
 * @author Thiemo Kreuz
 */
class UnsupportedTypeException extends DeserializationException {

	private $type;

	/**
	 * @param mixed $type
	 * @param string $message
	 * @param Throwable|null $previous
	 */
	public function __construct( $type, $message = '', ?Throwable $previous = null ) {
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
