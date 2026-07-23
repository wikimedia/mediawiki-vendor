<?php

namespace WebVTT\DOM;

use JsonSerializable;
use Stringable;
use WebVTT\DOM\Internal\VttStringableTrait;
use WebVTT\VttWriter;

/**
 * Represents a WebVTT file as an ordered sequence of blocks.
 *
 * The order of blocks is preserved and is significant for the file structure.
 *
 * According to the WebVTT standard, a file consists of:
 * 1. An optional Byte Order Mark (BOM).
 * 2. The string "WEBVTT".
 * 3. An optional text header (separated from WEBVTT by space or tab).
 * 4. Zero or more blocks separated by at least one empty line.
 *
 * Blocks can be of various types:
 * - Regions: Define rendering areas on the video viewport.
 * - Styles: CSS styling for the cues.
 * - Notes: Comments that are not rendered.
 * - Cues: The actual timed text content.
 *
 * In a valid WebVTT file, Regions and Styles must appear before any Cues.
 */
class VttFile implements JsonSerializable, Stringable {
	use VttStringableTrait;

	/**
	 * The blocks in the WebVTT file, in the order they appear in the file.
	 *
	 * @var VttBlock[]
	 */
	public array $blocks = [];

	/**
	 * The optional free-text following "WEBVTT" on the file's signature line.
	 */
	private string $description = '';

	/**
	 * Gets the optional text following "WEBVTT" on the file's signature line.
	 *
	 * @return string The description text, or an empty string if none was set.
	 */
	public function getDescription(): string {
		return $this->description;
	}

	/**
	 * Sets the optional text following "WEBVTT" on the file's signature line.
	 *
	 * @param string $value The description text.
	 */
	public function setDescription( string $value ): void {
		$this->description = $value;
	}

	/**
	 * Gets all blocks in the WebVTT file in their original order.
	 *
	 * @return VttBlock[] An array of WebVTT blocks.
	 */
	public function getBlocks(): array {
		return $this->blocks;
	}

	/**
	 * Gets all region blocks in the WebVTT file.
	 *
	 * @return VttRegion[] An array of WebVTT regions.
	 */
	public function getRegions(): array {
		// @phan-suppress-next-line PhanTypeMismatchReturn array_filter narrows to VttRegion.
		return array_values( array_filter( $this->blocks, static function ( $block ) {
			return $block instanceof VttRegion;
		} ) );
	}

	/**
	 * Gets all note blocks in the WebVTT file.
	 *
	 * @return VttNote[] An array of WebVTT notes.
	 */
	public function getNotes(): array {
		// @phan-suppress-next-line PhanTypeMismatchReturn array_filter narrows to VttNote.
		return array_values( array_filter( $this->blocks, static function ( $block ) {
			return $block instanceof VttNote;
		} ) );
	}

	/**
	 * Gets all stylesheet blocks in the WebVTT file.
	 *
	 * @return VttStyle[] An array of WebVTT styles.
	 */
	public function getStylesheets(): array {
		// @phan-suppress-next-line PhanTypeMismatchReturn array_filter narrows to VttStyle.
		return array_values( array_filter( $this->blocks, static function ( $block ) {
			return $block instanceof VttStyle;
		} ) );
	}

	/**
	 * Gets all cue blocks in the WebVTT file.
	 *
	 * @return VttCue[] An array of WebVTT cues.
	 */
	public function getCues(): array {
		// @phan-suppress-next-line PhanTypeMismatchReturn array_filter narrows to VttCue.
		return array_values( array_filter( $this->blocks, static function ( $block ) {
			return $block instanceof VttCue;
		} ) );
	}

	/**
	 * Adds a block to the WebVTT file.
	 *
	 * Blocks are generally added to the end of the file, except for Regions and Styles,
	 * which are inserted before any existing Cues to maintain a valid file structure.
	 *
	 * @param VttBlock $block The block to add.
	 */
	public function addBlock( VttBlock $block ): void {
		if ( $block instanceof VttRegion || $block instanceof VttStyle ) {
			// These can only be added before cue blocks
			foreach ( $this->blocks as $index => $existingBlock ) {
				if ( $existingBlock instanceof VttCue ) {
					array_splice( $this->blocks, $index, 0, [ $block ] );
					return;
				}
			}
		}
		$this->blocks[] = $block;
	}

	/**
	 * Returns the WebVTT file content in WebVTT format.
	 *
	 * @return string The WebVTT content.
	 */
	public function toVtt(): string {
		return ( new VttWriter( $this ) )->getContent();
	}

	/**
	 * Serializes the WebVTT file to an array for JSON serialization.
	 *
	 * @return array The serialized WebVTT data.
	 */
	public function jsonSerialize(): array {
		$results = [
			'cues' => $this->getCues(),
			'regions' => $this->getRegions(),
		];
		// We do this conditionally, because I didn't want to rewrite all the testcases
		if ( $this->description !== '' ) {
			$results['description'] = $this->description;
		}
		if ( $this->getNotes() ) {
			$results['notes'] = $this->getNotes();
		}
		if ( $this->getStylesheets() ) {
			$results['stylesheets'] = $this->getStylesheets();
		}
		return $results;
	}
}
