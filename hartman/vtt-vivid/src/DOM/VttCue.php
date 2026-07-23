<?php
/**
 * @phan-file-suppress PhanDeprecatedClassConstant Align::MIDDLE is intentionally used here to
 *   normalize the deprecated alignment keyword when serializing a cue.
 */

namespace WebVTT\DOM;

use JsonSerializable;
use WebVTT\DOM\Enums\Align;
use WebVTT\DOM\Enums\Direction;
use WebVTT\DOM\Enums\LineAlign;
use WebVTT\DOM\Enums\PositionAlign;
use WebVTT\DOM\Internal\SourceLineTrait;
use WebVTT\DOM\Internal\VttStringableTrait;
use WebVTT\DOM\Internal\VttTextSanitizer;
use WebVTT\Parser\CueTextParser;
use WebVTT\Validation\ValidationReporter;
use WebVTT\Validation\ValidatorTrait;

/**
 * Represents a single WebVTT cue.
 *
 * A WebVTT cue is a discrete entity of timed text. It consists of:
 * - An optional identifier.
 * - A start and end time (timestamps).
 * - Optional cue settings that control its positioning and appearance (vertical, line, position, size, align, region).
 * - The cue payload (text), which can contain simple HTML-like markup (c, i, b, u, ruby, rt, v, lang, timestamp).
 *
 * WebVTT cues are used to provide synchronized subtitles, captions, or other time-based metadata.
 */
class VttCue implements VttBlock, JsonSerializable {
	use ValidatorTrait;
	use SourceLineTrait;
	use VttStringableTrait;

	/**
	 * @param float $startTime The cue start time in seconds.
	 * @param float $endTime The cue end time in seconds.
	 * @param string $text The cue text.
	 * @param ValidationReporter|null $reporter Validation reporter.
	 */
	public function __construct(
		float $startTime, float $endTime, string $text, ?ValidationReporter $reporter = null
	) {
		$this->startTime = $startTime;
		$this->endTime = $endTime;
		$this->setText( $text );
		$this->setReporter( $reporter );
	}

	public const LINE_AUTO = 'auto';
	public const POSITION_AUTO = 'auto';

	private string $id = '';
	private bool $pauseOnExit = false;
	private float $startTime;
	private float $endTime;
	private string $text;
	/** @var CueText\Node[]|null */
	private ?array $contentNodes = null;
	private ?VttRegion $region = null;

	private Direction $vertical = Direction::HORIZONTAL;
	private bool $snapToLines = true;

	/** @var mixed */
	private $line = self::LINE_AUTO;
	/** @var LineAlign */
	private LineAlign $lineAlign = LineAlign::START;

	/** @var mixed */
	private $position = self::POSITION_AUTO;
	/** @var PositionAlign */
	private PositionAlign $positionAlign = PositionAlign::AUTO;
	/** @var float */
	private float $size = 100;
	/** @var Align */
	private Align $align = Align::CENTER;
	/** @var bool */
	public bool $hasBeenReset = false;

	/**
	 * Gets the cue identifier.
	 *
	 * @return string The cue identifier.
	 */
	public function getId(): string {
		return $this->id;
	}

	/**
	 * Sets the cue identifier.
	 *
	 * @param string $id The cue identifier.
	 */
	public function setId( string $id ): void {
		if ( str_contains( $id, "\0" ) ) {
			$this->reportWarning( 'Cue identifier cannot contain the null character (U+0000).' );
		}
		if ( preg_match( '/[\r\n]/', $id ) ) {
			$this->reportWarning(
				'Cue identifier cannot contain line breaks; they will be replaced with a space on output.'
			);
		}
		if ( str_contains( $id, '-->' ) ) {
			$this->reportWarning(
				'Cue identifier cannot contain the substring "-->"; it will be escaped to "--\>" on output.'
			);
		}
		$this->id = $id;
	}

	/**
	 * Gets the pause on exit flag.
	 *
	 * @return bool True if pause on exit is enabled, false otherwise.
	 */
	public function getPauseOnExit(): bool {
		return $this->pauseOnExit;
	}

	/**
	 * Sets the pause on exit flag.
	 *
	 * @param bool $value The pause on exit flag value.
	 */
	public function setPauseOnExit( bool $value ): void {
		$this->pauseOnExit = $value;
	}

	/**
	 * Gets the cue start time.
	 *
	 * @return float The cue start time in seconds.
	 */
	public function getStartTime(): float {
		return $this->startTime;
	}

	/**
	 * Sets the cue start time.
	 *
	 * @param float $value The cue start time in seconds.
	 */
	public function setStartTime( float $value ): void {
		if ( $value < 0 ) {
			$this->reportWarning( 'Start time must be a non-negative number.' );
		}
		$this->startTime = max( 0, $value );
		$this->hasBeenReset = true;
	}

	/**
	 * Gets the cue end time.
	 *
	 * @return float The cue end time in seconds.
	 */
	public function getEndTime(): float {
		return $this->endTime;
	}

	/**
	 * Sets the cue end time.
	 *
	 * @param float $value The cue end time in seconds.
	 */
	public function setEndTime( float $value ): void {
		if ( $value < 0 ) {
			$this->reportWarning( 'End time must be a non-negative number.' );
		}
		$this->endTime = max( 0, $value );
		$this->hasBeenReset = true;
	}

	/**
	 * Gets the cue text.
	 *
	 * @return string The cue text.
	 */
	public function getText(): string {
		return $this->text;
	}

	/**
	 * Sets the cue text.
	 *
	 * @param string $value The cue text.
	 */
	public function setText( string $value ): void {
		if ( str_contains( $value, "\0" ) ) {
			$this->reportWarning( 'Cue text cannot contain the null character (U+0000).' );
		}
		if ( str_contains( $value, '-->' ) ) {
			$this->reportWarning(
				'Cue text cannot contain the substring "-->"; it will be escaped to "--\>" on output.'
			);
		}
		if ( preg_match( '/(?:\r\n|\r|\n){2,}/', $value ) ) {
			$this->reportWarning(
				'Cue text cannot contain a blank line; it will be collapsed to a single line break on output.'
			);
		}
		if ( preg_match( '/^[\r\n]|[\r\n]$/', $value ) ) {
			$this->reportWarning(
				'Cue text cannot start or end with a line break; it will be trimmed on output.'
			);
		}

		$this->text = $value;
		$this->contentNodes = null;
		$this->hasBeenReset = true;
	}

	/**
	 * Parses the cue text into a tree of cue text node objects.
	 *
	 * Literal text becomes CueText\TextNode, in-cue timestamps become
	 * CueText\TimestampNode, the c/i/b/u/ruby/rt tags become CueText\ElementNode,
	 * and the v/lang tags become CueText\AnnotatedElementNode. The result is
	 * cached until the text changes.
	 *
	 * @see https://www.w3.org/TR/webvtt1/#webvtt-cue-text-parsing-rules
	 * @return CueText\Node[]
	 */
	public function getContentNodes(): array {
		if ( $this->contentNodes === null ) {
			$this->contentNodes = ( new CueTextParser( $this->reporter ) )->parse( $this->text );
		}
		return $this->contentNodes;
	}

	/**
	 * Sets the cue payload from a cue text node tree.
	 *
	 * This is the authoring counterpart of {@see getContentNodes()}: the tree is
	 * serialized to canonical WebVTT cue text and stored, so {@see getText()} and
	 * {@see toVtt()} stay consistent with the nodes. The tree is kept as the cached
	 * parse result, so {@see getContentNodes()} returns it without re-parsing.
	 *
	 * @param CueText\Node[] $nodes
	 */
	public function setContentNodes( array $nodes ): void {
		$this->setText( implode( '', $nodes ) );
		$this->contentNodes = $nodes;
	}

	/**
	 * Serializes the parsed cue text tree back into WebVTT cue text.
	 *
	 * Unlike {@see getText()}, which returns the original text verbatim, this
	 * returns the canonical form produced from the node tree: timestamps and the
	 * WebVTT character escapes are normalized, and unrecognized tags are dropped.
	 *
	 * @return string
	 */
	public function toCueText(): string {
		return implode( '', $this->getContentNodes() );
	}

	/**
	 * Gets the cue region.
	 *
	 * @return VttRegion|null The cue region, or null if not set.
	 */
	public function getRegion(): ?VttRegion {
		return $this->region;
	}

	/**
	 * Sets the cue region.
	 *
	 * @param VttRegion|null $value The cue region.
	 */
	public function setRegion( ?VttRegion $value ): void {
		$this->region = $value;
		$this->hasBeenReset = true;
	}

	/**
	 * Gets the cue vertical direction.
	 *
	 * @return Direction The vertical direction.
	 */
	public function getVertical(): Direction {
		return $this->vertical;
	}

	/**
	 * Sets the cue vertical direction.
	 *
	 * @param Direction $value The vertical direction.
	 */
	public function setVertical( Direction $value ): void {
		$this->vertical = $value;
		$this->hasBeenReset = true;
	}

	/**
	 * Gets the snap-to-lines flag.
	 *
	 * @return bool The snap-to-lines flag value.
	 */
	public function getSnapToLines(): bool {
		return $this->snapToLines;
	}

	/**
	 * Sets the snap-to-lines flag.
	 *
	 * @param bool $value The snap-to-lines flag value.
	 */
	public function setSnapToLines( bool $value ): void {
		$this->snapToLines = $value;
		$this->hasBeenReset = true;
	}

	/**
	 * Gets the cue line position.
	 *
	 * @return int|float|string The line position.
	 */
	public function getLine(): int|float|string {
		return $this->line;
	}

	/**
	 * Sets the cue line position.
	 *
	 * @param int|float|string $value The line position.
	 */
	public function setLine( int|float|string $value ): void {
		if ( !is_float( $value ) && !is_int( $value ) && $value !== self::LINE_AUTO ) {
			$this->reportWarning( 'An invalid number or illegal string was specified for line.' );
			return;
		}

		if ( $this->snapToLines && is_float( $value ) && (float)(int)$value !== $value ) {
			$this->reportWarning( 'Line number must be an integer when snapToLines is true.' );
		}

		if ( !$this->snapToLines && ( $value < 0 || $value > 100 ) ) {
			$this->reportWarning( 'Line percentage must be between 0 and 100.' );
			return;
		}

		$this->line = $value === self::LINE_AUTO ? self::LINE_AUTO : (float)$value;
		$this->hasBeenReset = true;
	}

	/**
	 * Gets the cue line alignment.
	 *
	 * @return LineAlign The line alignment.
	 */
	public function getLineAlign(): LineAlign {
		return $this->lineAlign;
	}

	/**
	 * Sets the cue line alignment.
	 *
	 * @param LineAlign $value The line alignment.
	 */
	public function setLineAlign( LineAlign $value ): void {
		$this->lineAlign = $value;
		$this->hasBeenReset = true;
	}

	/**
	 * Gets the cue text position.
	 *
	 * @return int|float|string The text position.
	 */
	public function getPosition(): int|float|string {
		return $this->position;
	}

	/**
	 * Sets the cue text position.
	 *
	 * @param int|float|string $value The text position.
	 */
	public function setPosition( int|float|string $value ): void {
		if ( $value === self::POSITION_AUTO ) {
			$this->position = self::POSITION_AUTO;
			$this->hasBeenReset = true;
			return;
		}
		if ( ( !is_float( $value ) && !is_int( $value ) ) || $value < 0 || $value > 100 ) {
			$this->reportWarning( 'Position must be between 0 and 100.' );
			return;
		}
		$this->position = (float)$value;
		$this->hasBeenReset = true;
	}

	/**
	 * Gets the cue position alignment.
	 *
	 * @return PositionAlign The position alignment.
	 */
	public function getPositionAlign(): PositionAlign {
		return $this->positionAlign;
	}

	/**
	 * Sets the cue position alignment.
	 *
	 * @param PositionAlign $value The position alignment.
	 */
	public function setPositionAlign( PositionAlign $value ): void {
		$this->positionAlign = $value;
		$this->hasBeenReset = true;
	}

	/**
	 * Gets the cue size.
	 *
	 * @return float The cue size.
	 */
	public function getSize(): float {
		return $this->size;
	}

	/**
	 * Sets the cue size.
	 *
	 * @param float $value The cue size.
	 */
	public function setSize( float $value ): void {
		if ( $value < 0 || $value > 100 ) {
			$this->reportWarning( 'Size must be between 0 and 100.' );
			return;
		}
		$this->size = $value;
		$this->hasBeenReset = true;
	}

	/**
	 * Gets the cue text alignment.
	 *
	 * @return Align The text alignment.
	 */
	public function getAlign(): Align {
		return $this->align;
	}

	/**
	 * Sets the cue text alignment.
	 *
	 * @param Align $value The text alignment.
	 */
	public function setAlign( Align $value ): void {
		$this->align = $value;
		$this->hasBeenReset = true;
	}

	/**
	 * Serializes the cue to an array for JSON serialization.
	 *
	 * @return array The serialized cue data.
	 */
	public function jsonSerialize(): array {
		return [
			'id' => $this->id,
			'pauseOnExit' => $this->pauseOnExit,
			'startTime' => $this->startTime,
			'endTime' => $this->endTime,
			'text' => $this->text,
			'region' => $this->region ?: '',
			'vertical' => $this->vertical->value,
			'snapToLines' => $this->snapToLines,
			'line' => $this->line,
			'lineAlign' => $this->lineAlign->value,
			'position' => $this->position,
			'positionAlign' => $this->positionAlign->value,
			'size' => $this->size,
			'align' => $this->align === Align::MIDDLE ? Align::CENTER->value : $this->align->value,
		];
	}

	/**
	 * Returns the cue in WebVTT format.
	 *
	 * @return string The cue in WebVTT format.
	 */
	public function toVtt(): string {
		$vtt = '';
		if ( $this->id !== '' ) {
			// A cue identifier is a single line per the WebVTT grammar and must not
			// contain "-->"; either would be misread as the cue's timing line and
			// silently drop the whole cue on re-parse.
			$vtt .= VttTextSanitizer::sanitizeLine( $this->id ) . "\n";
		}
		$vtt .= $this->formatTimestamp( $this->startTime ) . ' --> ' . $this->formatTimestamp( $this->endTime );

		$settings = [];
		if ( $this->region instanceof VttRegion ) {
			$settings[] = 'region:' . $this->region->getId();
		}
		if ( $this->vertical !== Direction::HORIZONTAL ) {
			$settings[] = 'vertical:' . $this->vertical->value;
		}
		if ( $this->line !== self::LINE_AUTO || !$this->snapToLines ) {
			$line = $this->line;
			if ( $this->line === self::LINE_AUTO ) {
				$line = self::LINE_AUTO;
			}
			$lineSuffix = !$this->snapToLines ? '%' : '';
			$lineAlignSuffix = $this->lineAlign !== LineAlign::START ? ',' . $this->lineAlign->value : '';
			$settings[] = 'line:' . $line . $lineSuffix . $lineAlignSuffix;
		}
		if ( $this->position !== self::POSITION_AUTO ) {
			// The parser resolves an unset positionAlign to the value implied by
			// align (per the spec's "auto" resolution), so positionAlign is only
			// genuinely explicit when it differs from that implied value.
			$impliedPositionAlign = match ( $this->align ) {
				Align::START, Align::LEFT => PositionAlign::START,
				Align::END, Align::RIGHT => PositionAlign::END,
				default => PositionAlign::CENTER,
			};
			$positionAlignSuffix = $this->positionAlign !== PositionAlign::AUTO
				&& $this->positionAlign !== $impliedPositionAlign
				? ',' . $this->positionAlign->value
				: '';
			$settings[] = 'position:' . $this->position . '%' . $positionAlignSuffix;
		}
		if ( $this->size !== 100.0 ) {
			$settings[] = 'size:' . $this->size . '%';
		}
		if ( $this->align !== Align::CENTER && $this->align !== Align::MIDDLE ) {
			$settings[] = 'align:' . $this->align->value;
		}

		if ( !empty( $settings ) ) {
			$vtt .= ' ' . implode( ' ', $settings );
		}

		// A blank line ends a cue block in WebVTT, so any embedded, leading, or
		// trailing blank line in the cue text would truncate the cue and orphan
		// the remaining content on re-parse; a leading break in particular abuts
		// the "\n" already appended above. Likewise any "-->" would be misread
		// as the start of a new timing line. There's no WebVTT escape for a
		// literal blank line, so it's sanitized the same way as the identifier.
		$vtt .= "\n" . VttTextSanitizer::sanitizeBlock( $this->text );

		return $vtt;
	}

	private function formatTimestamp( float $seconds ): string {
		$h = floor( $seconds / 3600 );
		$m = floor( ( (int)$seconds % 3600 ) / 60 );
		$s = floor( (int)$seconds % 60 );
		$ms = round( ( $seconds - floor( $seconds ) ) * 1000 );

		return sprintf( '%02d:%02d:%02d.%03d', (int)$h, (int)$m, (int)$s, (int)$ms );
	}
}
