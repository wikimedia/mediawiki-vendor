<?php

namespace WebVTT\Parser;

use WebVTT\Parser\Exceptions\BadTimeStampException;

/**
 * Parser for WebVTT timestamp strings.
 */
class TimeParser {
	private bool $strict;

	public function __construct( bool $strict = false ) {
		$this->strict = $strict;
	}

	/**
	 * Parse a WebVTT timestamp string into a numeric time value.
	 *
	 * @param string $timestamp The timestamp string.
	 * @param callable|null $reportValidationIssue Optional callback for reporting validation issues.
	 *
	 * @return float The parsed time in seconds.
	 * @throws BadTimeStampException If the timestamp format is invalid.
	 */
	public function parse( string $timestamp, ?callable $reportValidationIssue = null ): float {
		$timestamp = trim( $timestamp );
		if ( preg_match( '/^(?:(\d+):)?(\d{2}):(\d{2})\.(\d{3})$/', $timestamp, $matches ) ) {
			// Calculate the time in seconds
			$hours = (int)( $matches[1] ?? 0 );
			$minutes = (int)$matches[2];
			$seconds = (int)$matches[3];
			$milliseconds = (int)$matches[4];

			if ( $minutes >= 60 || $seconds >= 60 ) {
				throw new BadTimeStampException( 'Minutes and seconds must be less than 60.' );
			}

			if ( isset( $matches[1] ) && strlen( $matches[1] ) > 2 ) {
				if ( $reportValidationIssue ) {
					$reportValidationIssue( "Hours component should be 2 digits: '$timestamp'" );
				}
			}
		} elseif ( !$this->strict && preg_match( '/^(?:(\d+):)?(\d+):(\d+)(?:[.,](\d+))?$/', $timestamp, $matches ) ) {
			if ( $reportValidationIssue ) {
				$reportValidationIssue( "Invalid timestamp format: '$timestamp'" );
			}
			$hours = (int)( $matches[1] ?? 0 );
			$minutes = (int)$matches[2];
			$seconds = (int)$matches[3];
			// Interpret the fraction as a decimal of a second regardless of its
			// digit count, so ".5" is 500ms and ".12345" truncates to 123ms.
			$fraction = $matches[4] ?? '';
			$milliseconds = $fraction === '' ? 0 : (int)( (float)( '0.' . $fraction ) * 1000 );

			if ( ( ( $matches[1] ?? '' ) === '' ) && $minutes >= 60 ) {
				// With no hours field, a minutes value of 60+ is only meaningful if
				// the two components were really "hours:minutes", so shift them up
				// (e.g. "120:00.000" is read as 120 hours).
				$hours = $minutes;
				$minutes = $seconds;
				$seconds = 0;
			}
		} else {
			throw new BadTimeStampException();
		}

		return $hours * 3600 + $minutes * 60 + $seconds + $milliseconds / 1000;
	}
}
