<?php

namespace Serializers\Exceptions;

use RuntimeException;
use Throwable;

/**
 * @since 1.0
 *
 * @license GPL-2.0-or-later
 * @author Jeroen De Dauw < jeroendedauw@gmail.com >
 */
class SerializationException extends RuntimeException {

	public function __construct( $message = '', ?Throwable $previous = null ) {
		parent::__construct( $message, 0, $previous );
	}

}
