<?php

namespace Deserializers\Exceptions;

use Throwable;

/**
 * Indicates the objectType key is missing in the serialization.
 *
 * @since 1.0
 *
 * @license GPL-2.0-or-later
 * @author Jeroen De Dauw < jeroendedauw@gmail.com >
 */
class MissingTypeException extends DeserializationException {

	public function __construct( string $message = 'Type is missing', ?Throwable $previous = null ) {
		parent::__construct( $message, $previous );
	}
}
