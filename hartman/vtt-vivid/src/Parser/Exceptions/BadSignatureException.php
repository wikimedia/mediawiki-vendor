<?php

namespace WebVTT\Parser\Exceptions;

class BadSignatureException extends ParsingException {
	public function __construct() {
		parent::__construct( 'Malformed WebVTT signature.', 0 );
	}
}
