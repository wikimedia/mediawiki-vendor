<?php

namespace WebVTT\Parser\Exceptions;

class BadTimeStampException extends ParsingException {
	public function __construct( ?string $message = null ) {
		if ( $message ) {
			$message = "Malformed time stamp.\n$message";
		} else {
			$message = 'Malformed time stamp.';
		}
		parent::__construct( $message, 1 );
	}
}
