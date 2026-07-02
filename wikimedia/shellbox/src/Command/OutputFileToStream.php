<?php
declare( strict_types = 1 );

namespace Shellbox\Command;

use GuzzleHttp\Psr7\Utils;
use Psr\Http\Message\StreamInterface;
use Shellbox\FileUtils;
use Shellbox\Multipart\MultipartReader;

/**
 * Encapsulation of an output file that is copied to a stream
 */
class OutputFileToStream extends OutputFileWithContents {
	/**
	 * @internal
	 */
	public function __construct( private readonly StreamInterface $stream ) {
	}

	public function copyFromFile( $sourcePath ) {
		Utils::copyToStream( FileUtils::openInputFileStream( $sourcePath ), $this->stream );
		$this->setReceived();
	}

	public function getContents() {
		$this->stream->rewind();
		return $this->stream->getContents();
	}

	public function readFromMultipart( MultipartReader $multipartReader ) {
		$multipartReader->copyPartToStream( $this->stream );
		$this->setReceived();
	}
}
