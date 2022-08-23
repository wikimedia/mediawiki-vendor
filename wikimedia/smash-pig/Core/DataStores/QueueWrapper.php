<?php

namespace SmashPig\Core\DataStores;

use PHPQueue\Interfaces\FifoQueueStore;
use SmashPig\Core\Context;
use SmashPig\Core\UtcDate;
use SmashPig\CrmLink\Messages\SourceFields;

class QueueWrapper {

	/**
	 * @param string $queueName
	 * @param array|JsonSerializableObject $message
	 * @param bool $fallbackToDatabase When true, stash the message in a database table on queue failure
	 * @throws DataStoreException
	 * @throws \SmashPig\Core\ConfigurationKeyException
	 */
	public static function push( string $queueName, $message, bool $fallbackToDatabase = false ) {
		if ( $message instanceof JsonSerializableObject ) {
			$message = json_decode( $message->toJson(), true );
		}
		$queue = self::getQueue( $queueName );
		SourceFields::addToMessage( $message );
		try {
			$queue->push( $message );
		} catch ( \Exception $exception ) {
			if ( $fallbackToDatabase ) {
				// If the queue connection has failed, put the message in the 'damaged' table
				// with a retry date of now so the next requeue job will try to queue it again.
				DamagedDatabase::get()->storeMessage(
					$message,
					$queueName,
					$exception->getMessage(),
					$exception->getTraceAsString(),
					UtcDate::getUtcTimestamp()
				);
			} else {
				// If we're not trying to fall back to the database, just rethrow the exception
				throw $exception;
			}
		}
	}

	/**
	 * @param string $queueName
	 * @return FifoQueueStore
	 * @throws \SmashPig\Core\ConfigurationKeyException
	 */
	public static function getQueue( string $queueName ): FifoQueueStore {
		$config = Context::get()->getGlobalConfiguration();
		$key = "data-store/$queueName";

		// Examine the config node for a queue name
		$node = $config->val( $key );
		if (
			empty( $node['constructor-parameters'] ) ||
			empty( $node['constructor-parameters'][0]['queue'] )
		) {
			$nameParam = [
				'data-store' => [
					$queueName => [
						'constructor-parameters' => [
							[
								'queue' => $queueName
							]
						]
					]
				]
			];
			$config->override( $nameParam );
		}

		return $config->object( $key );
	}

}
