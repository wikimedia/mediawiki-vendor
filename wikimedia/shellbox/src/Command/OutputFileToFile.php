<?php
declare( strict_types = 1 );

namespace Shellbox\Command;

use Shellbox\FileUtils;
use Shellbox\Multipart\MultipartReader;

/**
 * Encapsulation of an output file that is copied to a local file
 */
class OutputFileToFile extends OutputFileWithContents {
	/**
	 * @internal
	 */
	public function __construct( private readonly string $path ) {
	}

	public function copyFromFile( $sourcePath ) {
		FileUtils::copy( $sourcePath, $this->path );
		$this->setReceived();
	}

	public function getContents() {
		return FileUtils::getContents( $this->path );
	}

	public function readFromMultipart( MultipartReader $multipartReader ) {
		$multipartReader->copyPartToStream( FileUtils::openOutputFileStream( $this->path ) );
		$this->setReceived();
	}
}
