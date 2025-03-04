<?php namespace SmashPig\Core;

use DateTime;
use DateTimeZone;
use Exception;

class UtcDate {
	public static function getUtcTimestamp( $dateString = 'now', $timeZone = 'UTC' ): int {
		$obj = new DateTime( $dateString, new DateTimeZone( $timeZone ) );
		return $obj->getTimestamp();
	}

	/**
	 * Format a UTC timestamp for database insertion
	 * @param string|int|null $timestamp defaults to time()
	 * @param string $format optional time format
	 * @return string
	 * @throws Exception
	 */
	public static function getUtcDatabaseString(
		$timestamp = null, $format = 'YmdHis'
	) {
		if ( $timestamp === null ) {
			$timestamp = time();
		}
		if ( is_numeric( $timestamp ) ) {
			// http://php.net/manual/en/datetime.formats.compound.php
			$timestamp = '@' . $timestamp;
		}
		$obj = new DateTime( $timestamp, new DateTimeZone( 'UTC' ) );
		return $obj->format( $format );
	}
}
