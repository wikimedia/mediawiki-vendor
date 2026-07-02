<?php
declare( strict_types = 1 );

namespace Shellbox\Command;

use Shellbox\FileUtils;

/**
 * Encapsulation of an input file that is copied from another file
 */
class InputFileFromFile extends InputFileWithContents {
	/**
	 * @internal
	 */
	public function __construct( private readonly string $path ) {
	}

	public function copyTo( $destPath ) {
		FileUtils::copy( $this->path, $destPath );
	}

	public function getStreamOrString() {
		return FileUtils::openInputFileStream( $this->path );
	}
}
