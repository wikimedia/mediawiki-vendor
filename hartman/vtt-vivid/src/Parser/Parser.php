<?php

namespace WebVTT\Parser;

use Closure;
use Exception;
use WebVTT\DOM\Enums\Scroll;
use WebVTT\DOM\VttCue;
use WebVTT\DOM\VttNote;
use WebVTT\DOM\VttRegion;
use WebVTT\DOM\VttStyle;
use WebVTT\Parser\Exceptions\BadSignatureException;
use WebVTT\Parser\Exceptions\ParsingException;
use WebVTT\Validation\CallbackValidationReporter;
use WebVTT\Validation\ValidationReporter;

/**
 * The main WebVTT parser class.
 *
 * This class implements a streaming-capable parser for WebVTT content as defined in the W3C WebVTT specification.
 * It follows the state machine approach for parsing the WebVTT file structure, including the signature,
 * optional headers (REGIONS, STYLES), and various blocks (Cues, Notes).
 *
 * WebVTT (Web Video Text Tracks) is a format for marking up text tracks (such as subtitles or captions)
 * for use with the HTML <track> element. A WebVTT file is a UTF-8 encoded text file that starts with
 * the signature "WEBVTT".
 *
 * @see https://www.w3.org/TR/webvtt1/
 */
class Parser {
	public ParserState $state = ParserState::INITIAL;

	private LineBuffer $lineBuffer;
	private bool $firstCueSeen = false;

	private ?VttNote $note = null;
	private ?VttCue $cue = null;
	/** @var string[] */
	private array $cueTextLines = [];
	private ?VttStyle $stylesheet = null;
	private ?Settings $regionSettings = null;
	private int $blockStartLine = 0;

	/** @var array<VttRegion> */
	private $regionList = [];

	private ?Closure $onSignature = null;
	private ?Closure $onNote = null;
	private ?Closure $onRegion = null;
	private ?Closure $onStylesheet = null;
	private ?Closure $onCue = null;
	private ?Closure $onFlush = null;
	private ?Closure $onParsingError = null;
	private ?ValidationReporter $reporter = null;

	private bool $strict = false;

	private TimeParser $timeParser;

	/**
	 * Parser constructor.
	 *
	 * @param ValidationReporter|null $reporter
	 */
	public function __construct( ?ValidationReporter $reporter = null ) {
		$this->lineBuffer = new LineBuffer();
		$this->timeParser = new TimeParser( $this->strict );
		$this->reporter = $reporter;
	}

	/**
	 * Sets the parser strictness.
	 *
	 * @param bool $strict Whether to use strict parsing mode.
	 */
	public function setStrict( bool $strict ): void {
		$this->strict = $strict;
		$this->timeParser = new TimeParser( $this->strict );
	}

	private function reportOrThrowError( Exception|string $e ): void {
		if ( $this->strict ) {
			if ( is_string( $e ) ) {
				throw new ParsingException( $e );
			}
			throw $e;
		}

		$message = $e;
		if ( $e instanceof Exception ) {
			$message = $e->getMessage();
		}

		if ( is_string( $message ) && !str_starts_with( $message, 'Line ' ) ) {
			$line = $this->lineBuffer->getLineNumber() ?: $this->blockStartLine;
			if ( $line > 0 ) {
				$message = "Line " . $line . ": " . $message;
			}
		}

		if ( $this->onParsingError ) {
			( $this->onParsingError )( $message );
		}
	}

	private function reportValidationIssue( string $e ): void {
		if ( $this->strict ) {
			throw new ParsingException( $e );
		}
		if ( $this->reporter ) {
			$this->reporter->report( $e );
		}
	}

	/**
	 * Parses a chunk of WebVTT data.
	 *
	 * @param string $data The WebVTT data chunk to parse.
	 */
	public function parse( string $data ): void {
		if ( $data ) {
			$this->lineBuffer->append( $data );
		}

		try {
			if ( $this->state === ParserState::INITIAL ) {
				$this->processInitialState();
				if ( $this->state === ParserState::INITIAL ) {
					return;
				}
			}

			while ( $this->lineBuffer->hasCompleteLine() ) {
				$this->blockStartLine = $this->lineBuffer->getLineNumber() + 1;
				$line = $this->lineBuffer->collectNextLine();
				$this->processState( $line );

				while ( $this->lineBuffer->alreadyCollectedLine() ) {
					$this->lineBuffer->setAlreadyCollectedLine( false );
					$this->processState( $line );
				}
			}
		} catch ( Exception $e ) {
			$this->reportOrThrowError( $e );

			if ( $this->state === ParserState::NOTE && $this->note && $this->onNote ) {
				( $this->onNote )( $this->note );
			}
			// If we are currently parsing a cue, report what we have.
			if ( $this->state === ParserState::CUETEXT && $this->cue && $this->onCue ) {
				( $this->onCue )( $this->cue );
			}
			$this->note = null;
			$this->cue = null;

			$this->state = $this->state === ParserState::INITIAL ? ParserState::BADWEBVTT : ParserState::BADCUE;
		}
	}

	private function processState( string $line ): void {
		switch ( $this->state ) {
			case ParserState::HEADER:
				$this->processHeaderState( $line );
				break;
			case ParserState::REGION:
				$this->processRegionState( $line );
				break;
			case ParserState::STYLE:
				$this->processStyleState( $line );
				break;
			case ParserState::NOTE:
				$this->processNoteState( $line );
				break;
			case ParserState::BLOCK:
				$this->processBlockState( $line );
				break;
			case ParserState::CUE:
				$this->processCueState( $line );
				break;
			case ParserState::CUETEXT:
				$this->processCueTextState( $line );
				break;
			case ParserState::BADCUE:
				$this->processBadCueState( $line );
				break;
		}
	}

	private function processInitialState(): void {
		if ( !$this->lineBuffer->hasCompleteLine() ) {
			return;
		}

		$line = $this->lineBuffer->collectNextLine();
		$line = preg_replace( '/^\xEF\xBB\xBF/', '', $line );

		if ( !str_starts_with( $line, 'WEBVTT' ) ) {
			$this->reportValidationIssue( "Invalid WebVTT signature: '$line'" );
			throw new BadSignatureException();
		}

		$match = preg_match( '/^WEBVTT([ \t](.*))?$/', $line, $matches );
		if ( !$match ) {
			// This handles cases like "WEBVTT garbage" where it starts with WEBVTT but has no space/tab
			$this->reportValidationIssue( "Invalid WebVTT signature: '$line'" );
			throw new BadSignatureException();
		}
		if ( isset( $matches[2] ) && $matches[2] !== '' && $this->onSignature ) {
			( $this->onSignature )( $matches[2] );
		}
		$this->state = ParserState::HEADER;
	}

	private function processHeaderState( string $line ): void {
		if ( !$line ) {
			// An empty line terminates the header and starts the body with blocks
			$this->state = ParserState::BLOCK;
		}
	}

	private function processNoteState( string $line ): void {
		if ( !$line ) {
			if ( $this->note && $this->onNote ) {
				$this->note->setSourceLine( $this->blockStartLine );
				call_user_func( $this->onNote, $this->note );
			}
			$this->note = null;
			$this->state = ParserState::BLOCK;
			return;
		}
		if ( !$this->note ) {
			$this->note = new VttNote( $line );
		} else {
			$this->note->addLine( $line );
		}
	}

	private function processRegionState( string $line ): void {
		if ( !$line ) {
			$this->state = ParserState::BLOCK;
			if ( $this->regionSettings && $this->regionSettings->has( 'id' ) ) {
				$region = new VttRegion();
				$region->setSourceLine( $this->blockStartLine );
				$region->setId( $this->regionSettings->get( 'id', '' ) );
				$region->setWidth( $this->regionSettings->get( 'width', 100 ) );
				$region->setLines( $this->regionSettings->get( 'lines', 3 ) );
				$region->setRegionAnchorX( $this->regionSettings->get( 'regionanchorX', 0 ) );
				$region->setRegionAnchorY( $this->regionSettings->get( 'regionanchorY', 100 ) );
				$region->setViewportAnchorX( $this->regionSettings->get( 'viewportanchorX', 0 ) );
				$region->setViewportAnchorY( $this->regionSettings->get( 'viewportanchorY', 100 ) );
				$region->setScroll( $this->regionSettings->get( 'scroll', Scroll::NONE ) );
				if ( $region ) {
					if ( $this->onRegion ) {
						( $this->onRegion )( $region );
					}
					$this->regionList[] = $region;
				}
			}
			$this->regionSettings = null;
			return;
		}
		if ( !$this->regionSettings ) {
			$this->regionSettings = new Settings();
		}
		$settingsParser = new SettingsParser( $this->regionList );
		$settingsParser->setReporter( $this->reporter );
		$settingsParser->parseRegionSettingsList( $line, $this->regionSettings );
	}

	private function processStyleState( string $line ): void {
		if ( !$line ) {
			$this->state = ParserState::BLOCK;

			if ( $this->stylesheet ) {
				$this->stylesheet->setSourceLine( $this->blockStartLine );
				if ( $this->onStylesheet ) {
					( $this->onStylesheet )( $this->stylesheet );
				}
				$this->stylesheet = null;
			}
			return;
		}

		if ( !$this->stylesheet ) {
			$this->stylesheet = new VttStyle( $line );
		} else {
			$this->stylesheet->addLine( $line );
		}
	}

	private function processBlockState( string $line ): void {
		if ( !$line ) {
			return;
		}

		if ( $this->lineIsRegion( $line ) ) {
			$this->state = ParserState::REGION;
			return;
		}
		if ( $this->lineIsStyle( $line ) ) {
			$this->state = ParserState::STYLE;
			return;
		}
		if ( $this->lineIsNote( $line ) ) {
			$this->state = ParserState::NOTE;
			if ( $this->lineIsInlineNote( $line ) ) {
				$this->processNoteState( preg_replace( "/^NOTE[ \t]/", '', $line, 1 ) );
			}
			return;
		}

		$this->cue = new VttCue( 0, 0, '' );
		$this->cueTextLines = [];
		// Route the cue's own validation (cue text tags, NUL, "-->" ) through the
		// parser so strict mode throws on it like every other validation issue.
		$this->cue->setReporter( new CallbackValidationReporter(
			fn ( string $message ) => $this->reportValidationIssue( $message )
		) );
		$this->state = ParserState::CUE;
		$this->firstCueSeen = true;

		if ( !$this->lineHasTiming( $line ) ) {
			// Block has no timing, so this might be the id of the cue
			$this->cue->setId( $line );
			return;
		}

		// Explicit fall-through to ParserState::CUE
		$this->processCueState( $line );
	}

	private function processCueState( string $line ): void {
		try {
			$this->parseCue( $line, $this->cue );
		} catch ( Exception $e ) {
			$this->reportOrThrowError( $e );
			$this->cue = null;
			$this->state = ParserState::BADCUE;
			return;
		}
		$this->state = ParserState::CUETEXT;
	}

	private function processCueTextState( string $line ): void {
		$hasSubstring = $this->lineHasTiming( $line );

		if ( !$line || $hasSubstring ) {
			if ( $hasSubstring ) {
				$this->lineBuffer->setAlreadyCollectedLine( true );
			}
			// Assemble the collected lines in one pass rather than re-scanning the
			// growing text on every appended line.
			$this->cue->setText( implode( "\n", $this->cueTextLines ) );
			$this->cueTextLines = [];
			// Eagerly parse the assembled cue text so its validation warnings
			// surface during file parsing rather than only on later rendering.
			$this->cue->getContentNodes();
			if ( $this->onCue ) {
				$this->cue->setSourceLine( $this->blockStartLine );
				( $this->onCue )( $this->cue );
			}
			$this->cue = null;
			$this->state = ParserState::BLOCK;
			return;
		}

		$this->cueTextLines[] = $line;
	}

	private function processBadCueState( string $line ): void {
		if ( !$line ) {
			$this->cue = null;
			$this->state = ParserState::BLOCK;
		}
	}

	/**
	 * Flushes any remaining data in the parser and completes the parsing process.
	 *
	 * @return void
	 */
	public function flush(): void {
		try {
			// Synthesize the end of the current cue, region, note, or style block
			if (
				$this->cue
				|| $this->note
				|| $this->regionSettings
				|| $this->stylesheet
				|| $this->state === ParserState::HEADER
			) {
				$this->lineBuffer->append( "\n\n" );
				$this->parse( '' );
			}
			// If we've flushed, parsed, and we're still on the initial state then
			// that means we don't have enough of the stream to parse the first line.
			if ( $this->state === ParserState::INITIAL ) {
				throw new BadSignatureException();
			}
		} catch ( Exception $e ) {
			$this->reportOrThrowError( $e );
		}
		if ( $this->onFlush ) {
			( $this->onFlush )();
		}
	}

	/**
	 * Parse the given cue line, timings, and settings, and update the cue object.
	 *
	 * @param string $line The line to parse.
	 * @param VttCue $cue The current cue object.
	 *
	 * @return void
	 * @throws ParsingException If cue parsing fails.
	 */
	public function parseCue( string $line, VttCue $cue ): void {
		$parts = preg_split( '/[ \t]*-->[ \t]*/u', trim( $line ), 2 );
		if ( count( $parts ) !== 2 ) {
			throw new ParsingException( "Invalid cue timing format: '$line'" );
		}

		if ( !preg_match( '/[ \t]-->[ \t]/u', $line ) ) {
			$this->reportValidationIssue( "Cue timing separator must be surrounded by spaces or tabs: '$line'" );
		}

		// Parse the start time
		$cue->setStartTime( $this->timeParser->parse( $parts[0], function ( $issue ) {
			$this->reportValidationIssue( $issue );
		} ) );

		// Parse the end time and settings
		[ $endTime, $settings ] = explode( ' ', $parts[1], 2 ) + [ 1 => '' ];
		$cue->setEndTime( $this->timeParser->parse( $endTime, function ( $issue ) {
			$this->reportValidationIssue( $issue );
		} ) );

		// Ensure the end time is greater than the start time
		if ( $cue->getEndTime() <= $cue->getStartTime() ) {
			$this->reportValidationIssue( "Cue end time must be greater than start time: '$line'" );
		}

		// Parse additional settings, if provided
		$settingsParser = new SettingsParser( $this->regionList );
		$settingsParser->setReporter( $this->reporter );
		$settingsParser->parseCueSettings( $settings, $cue );
	}

	/**
	 * Register a callback function that is triggered with the optional free-text
	 * following "WEBVTT" on the file's signature line, if present.
	 * @param Closure $callback
	 * @return void
	 */
	public function onSignature( Closure $callback ): void {
		$this->onSignature = $callback;
	}

	/**
	 * Register a callback function that is triggered for each parsed Note
	 * @param Closure $callback
	 * @return void
	 */
	public function onNote( Closure $callback ): void {
		$this->onNote = $callback;
	}

	/**
	 * Register a callback function that is triggered for each parsed Region definition
	 * @param Closure $callback
	 * @return void
	 */
	public function onRegion( Closure $callback ): void {
		$this->onRegion = $callback;
	}

	/**
	 * Register a callback function that is triggered for each parsed Style block
	 * @param Closure $callback
	 * @return void
	 */
	public function onStylesheet( Closure $callback ): void {
		$this->onStylesheet = $callback;
	}

	/**
	 * Register a callback function that is triggered for each parsed Cue
	 *
	 * @param Closure $callback
	 * @return void
	 */
	public function onCue( Closure $callback ): void {
		$this->onCue = $callback;
	}

	/**
	 * Register a callback function that is triggered after {@see Parser::flush()} was called
	 *
	 * @param Closure $callback
	 * @return void
	 */
	public function onFlush( Closure $callback ): void {
		$this->onFlush = $callback;
	}

	/**
	 * Register a callback function that is triggered when the parser encounters an error
	 * @param Closure $callback
	 * @return void
	 */
	public function onParsingError( Closure $callback ): void {
		$this->onParsingError = $callback;
	}

	/**
	 * Register a callback function that is triggered when the parser encounters a validation warning
	 * @param Closure $callback
	 * @return void
	 */
	public function onValidationWarning( Closure $callback ): void {
		$this->reporter = new CallbackValidationReporter( function ( $message ) use ( $callback ) {
			if ( !str_starts_with( $message, 'Line ' ) ) {
				$line = $this->lineBuffer->getLineNumber() ?: $this->blockStartLine;
				if ( $line > 0 ) {
					$message = "Line " . $line . ": " . $message;
				}
			}
			$callback( $message );
		} );
	}

	/**
	 * Sets a validation reporter.
	 *
	 * @param ValidationReporter $reporter
	 */
	public function setReporter( ValidationReporter $reporter ): void {
		$this->reporter = $reporter;
	}

	/**
	 * Checks if the line is a note.
	 *
	 * @param string $line The line to check.
	 * @return bool True if the line is a note, false otherwise.
	 */
	protected function lineIsNote( string $line ): bool {
		return preg_match( '/^NOTE/', $line ) > 0;
	}

	/**
	 * Checks if the line is an inline note.
	 *
	 * @param string $line The line to check.
	 * @return bool True if the line is an inline note, false otherwise.
	 */
	protected function lineIsInlineNote( string $line ): bool {
		return preg_match( '/^NOTE[ \t]/', $line ) > 0;
	}

	/**
	 * Checks if the line starts a region block.
	 *
	 * @param string $line The line to check.
	 * @return bool True if the line starts a region, false otherwise.
	 */
	protected function lineIsRegion( string $line ): bool {
		$isRegion = preg_match( '/^REGION([ \t]+|$)/', $line ) > 0;
		if ( $isRegion && $this->firstCueSeen ) {
			$this->reportValidationIssue( 'Style blocks must precede first cue' );
			return false;
		}
		return $isRegion;
	}

	/**
	 * Checks if the line starts a style block.
	 *
	 * @param string $line The line to check.
	 * @return bool True if the line starts a style block, false otherwise.
	 */
	protected function lineIsStyle( string $line ): bool {
		$isStyle = preg_match( '/^STYLE([ \t]+|$)/', $line ) > 0;
		if ( $isStyle && $this->firstCueSeen ) {
			$this->reportValidationIssue( 'Style blocks must precede first cue' );
			return false;
		}
		return $isStyle;
	}

	/**
	 * Checks if the line contains a timing separator.
	 *
	 * @param string $line The line to check.
	 * @return bool True if the line contains a timing separator, false otherwise.
	 */
	protected function lineHasTiming( string $line ): bool {
		if ( str_contains( $line, '-->' ) ) {
			if ( in_array( $this->state, [
				ParserState::HEADER,
				ParserState::REGION,
				ParserState::STYLE,
				ParserState::NOTE,
				ParserState::BADCUE
			], true ) ) {
				return false;
			}

			// Per the WebVTT spec's "collect a WebVTT block" algorithm, a line
			// containing "-->" ends a cue identifier (step 31) or cue text
			// (cue text loop step) unconditionally, regardless of what
			// precedes it. There is no digit-prefix requirement.

			// In CUE state, we are currently parsing a timing line.
			// This might be called if we are checking if the line IS a timing line.
			// But if we are ALREADY in CUE state, we expect a timing line.

			// If it matches a timing separator strictly, it's timing.
			if (
				str_contains( $line, ' -->' )
				|| str_contains( $line, '-->' . "\t" )
				|| str_contains( $line, "\t" . '-->' )
			) {
				return true;
			}

			return !$this->strict;
		}
		return false;
	}
}
