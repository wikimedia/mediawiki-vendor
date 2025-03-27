<?php
namespace SmashPig\CrmLink\Messages;

use SmashPig\Core\Context;
use SmashPig\Core\UtcDate;

class SourceFields {
	/**
	 * Add fields to a queue message to identify the source
	 *
	 * @param array &$message
	 */
	public static function addToMessage( &$message ) {
		$context = Context::get();
		$message['source_name'] = $context->getSourceName();
		$message['source_type'] = $context->getSourceType();
		$message['source_host'] = gethostname();
		$message['source_run_id'] = getmypid();
		$message['source_version'] = $context->getSourceRevision();
		$message['source_enqueued_time'] = UtcDate::getUtcTimestamp();
	}

	/**
	 * Remove and return the source fields from a queue message
	 *
	 * @param array &$message
	 * @return array
	 */
	public static function removeFromMessage( &$message ) {
		$sourceFields = [];
		$suffixes = [
			'name', 'type', 'host', 'run_id', 'version', 'enqueued_time'
		];
		foreach ( $suffixes as $suffix ) {
			$name = 'source_' . $suffix;
			$sourceFields[$name] = $message[$name];
			unset( $message[$name] );
		}
		return $sourceFields;
	}
}
