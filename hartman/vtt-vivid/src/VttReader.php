<?php

namespace WebVTT;

use JsonSerializable;
use RuntimeException;
use WebVTT\DOM\VttFile;
use WebVTT\Parser\Parser;

class VttReader implements JsonSerializable {
	public VttFile $vttFile;
	public array $errors = [];
	private bool $strict = false;

	/** @var resource */
	private $source;

	/**
	 * @var bool Whether we should close the source after parsing
	 */
	private bool $shouldClose = false;

	/**
	 * @param resource $source Stream resource
	 */
	public function __construct(
		$source
	) {
		// phpcs:ignore MediaWiki.Usage.ForbiddenFunctions.is_resource
		if ( !is_resource( $source ) ) {
			throw new RuntimeException( 'Source must be a resource.' );
		}
		$this->source = $source;
		$this->vttFile = new VttFile();
	}

	/**
	 * Create a VttFileReader from a file path
	 *
	 * @param string $filename
	 * @return self
	 */
	public static function fromFile( string $filename ): self {
		$stream = fopen( $filename, 'rb' );
		if ( $stream === false ) {
			throw new RuntimeException( 'Failed to open file: ' . $filename );
		}
		$instance = new self( $stream );
		$instance->shouldClose = true;
		return $instance;
	}

	/**
	 * Create a VttFileReader from a string
	 *
	 * @param string $content
	 * @return self
	 */
	public static function fromString( string $content ): self {
		$stream = fopen( 'php://temp', 'rb+' );
		if ( $stream === false ) {
			throw new RuntimeException( 'Failed to create temporary stream.' );
		}
		fwrite( $stream, $content );
		rewind( $stream );
		$instance = new self( $stream );
		$instance->shouldClose = true;
		return $instance;
	}

	public function parse(): void {
		$parser = new Parser();
		$parser->setStrict( $this->strict );
		$this->registerParserCallbacks( $parser );

		try {
			$this->parseStream( $this->source, $parser );
		} finally {
			if ( $this->shouldClose ) {
				fclose( $this->source );
			}
		}

		$parser->flush();
	}

	private function registerParserCallbacks( Parser $parser ): void {
		$parser->onSignature( function ( $description ) {
			$this->vttFile->setDescription( $description );
		} );
		$parser->onNote( function ( $note ) {
			$this->vttFile->addBlock( $note );
		} );
		$parser->onRegion( function ( $region ) {
			$this->vttFile->addBlock( $region );
		} );
		$parser->onStylesheet( function ( $stylesheet ) {
			$this->vttFile->addBlock( $stylesheet );
		} );
		$parser->onCue( function ( $cue ) {
			$this->vttFile->addBlock( $cue );
		} );
		$parser->onParsingError( function ( $error ) {
			$this->errors[] = $error;
		} );
		$parser->onValidationWarning( function ( $warning ) {
			$this->errors[] = $warning;
		} );
	}

	/**
	 * @param resource $stream
	 * @param Parser $parser
	 */
	private function parseStream( $stream, Parser $parser ): void {
		while ( !feof( $stream ) ) {
			$chunk = fread( $stream, 8192 );
			if ( $chunk !== false ) {
				$parser->parse( $chunk );
			}
		}
	}

	public function getVTTFile(): VttFile {
		return $this->vttFile;
	}

	public function setStrict( bool $strict ): void {
		$this->strict = $strict;
	}

	public function jsonSerialize(): array {
		$result = $this->vttFile->jsonSerialize();
		if ( count( $this->errors ) > 0 ) {
			$result['errors'] = $this->errors;
		}
		return $result;
	}
}
