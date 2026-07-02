<?php
declare( strict_types = 1 );

namespace Shellbox\Command;

/**
 * An output glob for files that are written to a local directory
 */
class OutputGlobToFile extends OutputGlob {
	/**
	 * @internal
	 */
	public function __construct(
		string $prefix,
		string $extension,
		private readonly string $destDir,
	) {
		parent::__construct( $prefix, $extension );
	}

	public function getOutputFile( $boxedName ) {
		$instance = new OutputFileToFile( $this->destDir . '/' . basename( $boxedName ) );
		$this->files[$boxedName] = $instance;
		return $instance;
	}
}
