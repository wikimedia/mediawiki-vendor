<?php

namespace WebVTT;

use WebVTT\DOM\Internal\VttTextSanitizer;
use WebVTT\DOM\VttFile;

class VttWriter {

	public function __construct(
		private readonly VttFile $vttFile
	) {
	}

	public function write( string $filename ): bool {
		$content = $this->getContent();
		return file_put_contents( $filename, $content ) !== false;
	}

	public function getContent(): string {
		$description = $this->vttFile->getDescription();
		$vtt = $description !== ''
			? 'WEBVTT ' . VttTextSanitizer::sanitizeLine( $description ) . "\n"
			: "WEBVTT\n";

		$blocks = $this->vttFile->getBlocks();
		foreach ( $blocks as $block ) {
			$vtt .= "\n" . $block->toVtt() . "\n";
		}

		return rtrim( $vtt ) . "\n";
	}
}
