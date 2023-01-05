<?php
namespace SmashPig\CrmLink\Messages;

use SmashPig\Core\UtcDate;

class DateFields {

	/**
	 * @param array $message A message from donation queues
	 * @param int $default Value to return when message has no dates
	 * @return int The unix timestamp at which the message was originally
	 *  enqueued, or $default if no date information exists
	 */
	public static function getOriginalDateOrDefault( $message, $default = 0 ) {
		// This is the actual queued time
		if ( isset( $message['source_enqueued_time'] ) ) {
			// This is only ever set to the numeric timestamp
			return $message['source_enqueued_time'];
		}
		// Message missing source field might still have a date
		if ( isset( $message['date'] ) ) {
			// This field is sometimes not a timestamp
			// FIXME: normalize PayPal recurring before queueing!
			if ( is_int( $message['date'] ) ) {
				return $message['date'];
			}
			// Try parsing non-numeric things
			$parsedTimestamp = UtcDate::getUtcTimestamp(
				$message['date']
			);
			if ( $parsedTimestamp !== null ) {
				return $parsedTimestamp;
			}
		}
		return $default;
	}
}
