<?php
/**
 * @phan-file-suppress PhanDeprecatedClassConstant Deprecated align keywords are intentionally
 *   recognised here to parse and map legacy cue settings.
 */

namespace WebVTT\Parser;

use WebVTT\DOM\Enums\Align;
use WebVTT\DOM\Enums\Direction;
use WebVTT\DOM\Enums\LineAlign;
use WebVTT\DOM\Enums\PositionAlign;
use WebVTT\DOM\Enums\Scroll;
use WebVTT\DOM\VttCue;
use WebVTT\DOM\VttRegion;
use WebVTT\Validation\ValidationReporter;
use WebVTT\Validation\ValidatorTrait;

/**
 * Parser for WebVTT cue and region settings.
 */
class SettingsParser {
	use ValidatorTrait;

	/** @var array<VttRegion> */
	private array $regionList;

	/**
	 * @param array<VttRegion> $regionList
	 * @param ValidationReporter|null $reporter
	 */
	public function __construct( array $regionList = [], ?ValidationReporter $reporter = null ) {
		$this->regionList = $regionList;
		$this->setReporter( $reporter );
	}

	/**
	 * Parse and apply cue settings.
	 *
	 * @param string $settingsString The settings string.
	 * @param VttCue $cue The cue object to update.
	 *
	 * @return void
	 */
	public function parseCueSettings( string $settingsString, VttCue $cue ): void {
		$cueSettings = new Settings();
		$optionsParser = new OptionsParser();
		$optionsParser->parse( $settingsString, function ( $key, $value ) use ( $cueSettings ) {
			switch ( $key ) {
				case 'region':
					foreach ( $this->regionList as $region ) {
						if ( $region->getId() === $value ) {
							$cueSettings->set( $key, $region );
						}
					}
					break;
				case 'vertical':
					if ( $value === '' ) {
						$this->reportValidationIssue( "Invalid vertical setting: '$value'" );
						break;
					}
					if ( !$cueSettings->enum( $key, $value, Direction::class ) ) {
						$this->reportValidationIssue( "Invalid vertical setting: '$value'" );
					}
					break;
				case 'line':
					$values = explode( ',', $value );
					$cueSettings->integer( $key, $values[0] );
					if ( $cueSettings->percent( $key, $values[0] ) ) {
						$cueSettings->set( 'snapToLines', false );
					}
					$cueSettings->alt( $key, $values[0], [ VttCue::LINE_AUTO ] );
					if ( !$cueSettings->has( $key ) ) {
						$this->reportValidationIssue( "Invalid line setting: '{$values[0]}'" );
					}
					if ( count( $values ) === 2 ) {
						if ( !$cueSettings->enum( 'lineAlign', $values[1], LineAlign::class ) ) {
							$this->reportValidationIssue( "Invalid line alignment: '{$values[1]}'" );
						}
					}
					break;
				case 'position':
					$values = explode( ',', $value );
					$cueSettings->percent( $key, $values[0] );
					$cueSettings->alt( $key, $values[0], [ VttCue::POSITION_AUTO ] );
					if ( !$cueSettings->has( $key ) ) {
						$this->reportValidationIssue( "Invalid position setting: '{$values[0]}'" );
					}
					if ( count( $values ) === 2 ) {
						if ( !$cueSettings->enum( 'positionAlign', $values[1], PositionAlign::class ) ) {
							$this->reportValidationIssue( "Invalid position alignment: '{$values[1]}'" );
						} else {
							$positionAlign = $cueSettings->get( 'positionAlign' );
							$deprecatedValues = [ PositionAlign::START, PositionAlign::CENTER, PositionAlign::END ];
							if ( in_array( $positionAlign, $deprecatedValues, true ) ) {
								$this->reportValidationIssue(
									"Deprecated position alignment keyword used: '{$values[1]}'"
								);
							}
						}
					}
					break;
				case 'size':
					$cueSettings->percent( $key, $value );
					if ( !$cueSettings->has( $key ) ) {
						$this->reportValidationIssue( "Invalid size setting: '$value'" );
					}
					break;
				case 'align':
					if ( $value === '' ) {
						$this->reportValidationIssue( "Invalid alignment setting: '$value'" );
						break;
					}
					if ( !$cueSettings->enum( $key, $value, Align::class ) ) {
						$this->reportValidationIssue( "Invalid alignment setting: '$value'" );
					} else {
						$align = $cueSettings->get( $key );
						if ( $align === Align::LEFT || $align === Align::RIGHT || $align === Align::MIDDLE ) {
							$this->reportValidationIssue( "Deprecated alignment keyword used: '$value'" );
						}
					}
					break;
				default:
					$this->reportValidationIssue( "Unknown cue setting: '$key'" );
			}
		}, ':', '/[ \t]/' );

		// Apply default values for any missing fields.
		// Per spec, a region setting is ignored if the cue also has a 'vertical',
		// 'line', or 'size' cue setting; the cue then renders on its own instead
		// of as part of the region.
		$hasRegionIncompatibleSetting = $cueSettings->has( 'vertical' )
			|| $cueSettings->has( 'line' )
			|| $cueSettings->has( 'size' );
		$cue->setRegion( $hasRegionIncompatibleSetting ? null : $cueSettings->get( 'region', null ) );
		$cue->setVertical( $cueSettings->get( 'vertical', Direction::HORIZONTAL ) );
		$cue->setSnapToLines( $cueSettings->get( 'snapToLines', true ) );
		$cue->setLine( $cueSettings->get( 'line', VttCue::LINE_AUTO ) );
		$cue->setLineAlign( $cueSettings->get( 'lineAlign', LineAlign::START ) );
		$cue->setSize( $cueSettings->get( 'size', 100 ) );
		$cue->setAlign( $cueSettings->get( 'align', Align::CENTER ) );
		$cue->setPosition( $cueSettings->get( 'position', VttCue::POSITION_AUTO ) );

		if ( $cueSettings->has( 'positionAlign' ) ) {
			$cue->setPositionAlign( $cueSettings->get( 'positionAlign' ) );
		} else {
			$cue->setPositionAlign( $cueSettings->get( 'positionAlign', [
				Align::START->value => PositionAlign::START,
				Align::LEFT->value => PositionAlign::START,
				Align::CENTER->value => PositionAlign::CENTER,
				Align::MIDDLE->value => PositionAlign::CENTER,
				Align::END->value => PositionAlign::END,
				Align::RIGHT->value => PositionAlign::END,
			], $cue->getAlign()->value ) );
		}
	}

	public function parseRegionSettingsList( string $input, Settings $settings ): void {
		$optionsParser = new OptionsParser();
		$optionsParser->parse( $input, function ( $k, $v ) use ( $settings ) {
			switch ( $k ) {
				case 'id':
					$settings->set( $k, $v );
					break;

				case 'width':
					if ( !$settings->percent( $k, $v ) ) {
						$this->reportValidationIssue( "Invalid region width: '$v'" );
					}
					break;

				case 'lines':
					$settings->integer( $k, $v );
					if ( !$settings->has( $k ) ) {
						$this->reportValidationIssue( "Invalid region lines: '$v'" );
					}
					break;

				case 'regionanchor':
				case 'viewportanchor':
					$xy = explode( ',', $v );
					if ( count( $xy ) !== 2 ) {
						$this->reportValidationIssue( "Invalid anchor format: '$v'" );
						break;
					}
					// Create a temporary settings object to validate x and y.
					$anchor = new Settings();
					$anchor->percent( 'x', $xy[0] );
					$anchor->percent( 'y', $xy[1] );

					if ( !$anchor->has( 'x' ) || !$anchor->has( 'y' ) ) {
						$this->reportValidationIssue( "Invalid anchor values: '$v'" );
						break;
					}

					$settings->set( $k . 'X', $anchor->get( 'x' ) );
					$settings->set( $k . 'Y', $anchor->get( 'y' ) );
					break;

				case 'scroll':
					if ( $v === '' ) {
						$this->reportValidationIssue( "Invalid scroll value: '$v'" );
						break;
					}
					if ( !$settings->enum( $k, $v, Scroll::class ) ) {
						$this->reportValidationIssue( "Invalid scroll value: '$v'" );
					}
					break;
				default:
					$this->reportValidationIssue( "Unknown region setting: '$k'" );
			}
		}, ':', '/[ \t]/' );
	}

	private function reportValidationIssue( string $message ): void {
		$this->reportWarning( $message );
	}
}
