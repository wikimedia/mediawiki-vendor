<?php
declare( strict_types = 1 );

namespace Shellbox\Command;

/**
 * A BoxedResult subclass used by ServerBoxedExecutor, providing simplified
 * output file handling.
 *
 * @internal
 */
class ServerBoxedResult extends BoxedResult {
	/** @var string[] */
	private array $fileNames = [];
	/** @var string[] */
	private array $sentFileNames = [];

	public function getFileNames() {
		return $this->fileNames;
	}

	public function getReceivedFileNames() {
		// Our sent files are your received files. Or to put it another way,
		// the client defines the terminology in the public interface.
		return $this->sentFileNames;
	}

	/**
	 * @param string[] $fileNames
	 * @param string[] $sentFileNames
	 */
	public function setFileNames( $fileNames, $sentFileNames ) {
		$this->fileNames = $fileNames;
		$this->sentFileNames = $sentFileNames;
	}
}
