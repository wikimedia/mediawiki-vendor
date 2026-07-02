<?php
declare( strict_types = 1 );

namespace Shellbox\Command;

use Shellbox\FileUtils;

/**
 * Encapsulation of an input file that comes from a string
 */
class InputFileFromString extends InputFileWithContents {
	/**
	 * @internal
	 */
	public function __construct( private readonly string $contents ) {
	}

	public function copyTo( $destPath ) {
		FileUtils::putContents( $destPath, $this->contents );
	}

	public function getStreamOrString() {
		return $this->contents;
	}
}
