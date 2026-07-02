<?php
declare( strict_types = 1 );

namespace Shellbox\Command;

use Shellbox\ShellboxError;

class ValidationError extends ShellboxError {
	public function __construct( string $message ) {
		parent::__construct( "Shellbox command validation error: $message" );
	}
}
