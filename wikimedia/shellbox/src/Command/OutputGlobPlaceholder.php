<?php
declare( strict_types = 1 );

namespace Shellbox\Command;

/**
 * @internal
 */
class OutputGlobPlaceholder extends OutputGlob {
	public function getOutputFile( $boxedName ) {
		return new OutputFilePlaceholder;
	}
}
