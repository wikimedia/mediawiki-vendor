<?php

namespace WebVTT\DOM;

use Closure;
use JsonSerializable;
use WebVTT\DOM\Enums\Scroll;
use WebVTT\DOM\Internal\SourceLineTrait;
use WebVTT\DOM\Internal\VttStringableTrait;
use WebVTT\DOM\Internal\VttTextSanitizer;
use WebVTT\Validation\CallbackValidationReporter;
use WebVTT\Validation\ValidatorTrait;

/**
 * Represents a WebVTT region.
 *
 * A WebVTT region provides a fixed area in the video viewport where cues can be rendered.
 * This is particularly useful for roll-up captions or to ensure cues stay within a specific area.
 *
 * Region settings include:
 * - id: A unique identifier.
 * - width: The width of the region as a percentage of the video width.
 * - lines: The number of lines the region displays.
 * - regionanchor: The point within the region that is fixed to the viewport anchor.
 * - viewportanchor: The point within the video viewport where the region is anchored.
 * - scroll: Whether the cues in the region scroll up (roll-up) or just replace each other.
 */
class VttRegion implements VttBlock, JsonSerializable {
	use SourceLineTrait;
	use ValidatorTrait;
	use VttStringableTrait;

	private string $id;
	private float $width = 100;
	private int $lines = 3;
	private float $regionAnchorX = 0;
	private float $regionAnchorY = 100;
	private float $viewportAnchorX = 0;
	private float $viewportAnchorY = 100;
	/** @var Scroll */
	private Scroll $scroll = Scroll::NONE;

	/**
	 * Sets the validation warning callback.
	 *
	 * @param Closure|null $callback The callback function.
	 * @deprecated Use setReporter() instead.
	 */
	public function setOnValidationWarning( ?Closure $callback ): void {
		if ( $callback ) {
			$this->setReporter( new CallbackValidationReporter( $callback ) );
		} else {
			$this->setReporter( null );
		}
	}

	/**
	 * Gets the region identifier.
	 *
	 * @return string The region identifier.
	 */
	public function getId(): string {
		return $this->id;
	}

	/**
	 * Sets the region identifier.
	 *
	 * @param string $value The region identifier.
	 */
	public function setId( string $value ): void {
		if ( str_contains( $value, '-->' ) ) {
			$this->reportValidationWarning(
				'Region identifier cannot contain the substring "-->"; it will be escaped to "--\>" on output.'
			);
		}
		if ( preg_match( '/[\r\n]/', $value ) ) {
			$this->reportValidationWarning(
				'Region identifier cannot contain line breaks; they will be replaced with a space on output.'
			);
		}
		$this->id = $value;
	}

	/**
	 * Gets the region width.
	 *
	 * @return float The region width in percentage.
	 */
	public function getWidth(): float {
		return $this->width;
	}

	/**
	 * Sets the region width.
	 *
	 * @param float $value The region width in percentage.
	 */
	public function setWidth( float $value ): void {
		if ( $value < 0 || $value > 100 ) {
			$this->reportValidationWarning( 'Width must be between 0 and 100.' );
			return;
		}
		$this->width = $value;
	}

	/**
	 * Gets the number of lines in the region.
	 *
	 * @return int The number of lines.
	 */
	public function getLines(): int {
		return $this->lines;
	}

	/**
	 * Sets the number of lines in the region.
	 *
	 * @param int $value The number of lines.
	 */
	public function setLines( int $value ): void {
		if ( $value < 0 ) {
			$this->reportValidationWarning( 'Lines must be a non-negative integer.' );
			return;
		}
		$this->lines = $value;
	}

	/**
	 * Gets the region anchor X coordinate.
	 *
	 * @return float The X coordinate in percentage.
	 */
	public function getRegionAnchorX(): float {
		return $this->regionAnchorX;
	}

	/**
	 * Sets the region anchor X coordinate.
	 *
	 * @param float $value The X coordinate in percentage.
	 */
	public function setRegionAnchorX( float $value ): void {
		if ( $value < 0 || $value > 100 ) {
			$this->reportValidationWarning( 'RegionAnchorX must be between 0 and 100.' );
			return;
		}
		$this->regionAnchorX = $value;
	}

	/**
	 * Gets the region anchor Y coordinate.
	 *
	 * @return float The Y coordinate in percentage.
	 */
	public function getRegionAnchorY(): float {
		return $this->regionAnchorY;
	}

	/**
	 * Sets the region anchor Y coordinate.
	 *
	 * @param float $value The Y coordinate in percentage.
	 */
	public function setRegionAnchorY( float $value ): void {
		if ( $value < 0 || $value > 100 ) {
			$this->reportValidationWarning( 'RegionAnchorY must be between 0 and 100.' );
			return;
		}
		$this->regionAnchorY = $value;
	}

	/**
	 * Gets the viewport anchor X coordinate.
	 *
	 * @return float The X coordinate in percentage.
	 */
	public function getViewportAnchorX(): float {
		return $this->viewportAnchorX;
	}

	/**
	 * Sets the viewport anchor X coordinate.
	 *
	 * @param float $value The X coordinate in percentage.
	 */
	public function setViewportAnchorX( float $value ): void {
		if ( $value < 0 || $value > 100 ) {
			$this->reportValidationWarning( 'ViewportAnchorX must be between 0 and 100.' );
			return;
		}
		$this->viewportAnchorX = $value;
	}

	/**
	 * Gets the viewport anchor Y coordinate.
	 *
	 * @return float The Y coordinate in percentage.
	 */
	public function getViewportAnchorY(): float {
		return $this->viewportAnchorY;
	}

	/**
	 * Sets the viewport anchor Y coordinate.
	 *
	 * @param float $value The Y coordinate in percentage.
	 */
	public function setViewportAnchorY( float $value ): void {
		if ( $value < 0 || $value > 100 ) {
			$this->reportValidationWarning( 'ViewportAnchorY must be between 0 and 100.' );
			return;
		}
		$this->viewportAnchorY = $value;
	}

	/**
	 * Gets the scroll setting.
	 *
	 * @return Scroll The scroll setting.
	 */
	public function getScroll(): Scroll {
		return $this->scroll;
	}

	/**
	 * Sets the scroll setting.
	 *
	 * @param Scroll $value The scroll setting.
	 */
	public function setScroll( Scroll $value ): void {
		$this->scroll = $value;
	}

	private function reportValidationWarning( string $message ): void {
		$this->reportWarning( $message );
	}

	/**
	 * Serializes the region to an array for JSON serialization.
	 *
	 * @return array The serialized region data.
	 */
	public function jsonSerialize(): array {
		return [
			'width' => $this->width,
			'lines' => $this->lines,
			'regionAnchorX' => $this->regionAnchorX,
			'regionAnchorY' => $this->regionAnchorY,
			'viewportAnchorX' => $this->viewportAnchorX,
			'viewportAnchorY' => $this->viewportAnchorY,
			'scroll' => $this->scroll->value,
		];
	}

	/**
	 * Returns the region in WebVTT format.
	 *
	 * @return string The region in WebVTT format.
	 */
	public function toVtt(): string {
		$vtt = "REGION\n";
		if ( isset( $this->id ) && $this->id !== '' ) {
			// A region identifier must not contain "-->" or a line break per the
			// WebVTT grammar; sanitize it the same way the cue identifier is.
			$vtt .= 'id:' . VttTextSanitizer::sanitizeLine( $this->id ) . "\n";
		}
		$vtt .= 'width:' . $this->width . "%\n";
		$vtt .= 'lines:' . $this->lines . "\n";
		$vtt .= 'regionanchor:' . $this->regionAnchorX . '%,' . $this->regionAnchorY . "%\n";
		$vtt .= 'viewportanchor:' . $this->viewportAnchorX . '%,' . $this->viewportAnchorY . "%\n";
		if ( $this->scroll !== Scroll::NONE ) {
			$vtt .= 'scroll:' . $this->scroll->value . "\n";
		}
		return rtrim( $vtt );
	}
}
