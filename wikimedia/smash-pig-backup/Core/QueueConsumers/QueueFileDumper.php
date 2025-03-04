<?php namespace SmashPig\Core\QueueConsumers;

use RuntimeException;
use SmashPig\Core\UtcDate;

class QueueFileDumper extends BaseQueueConsumer {

	/**
	 * @var resource
	 */
	protected $file;

	/**
	 * @var array
	 */
	protected $conditions;

	protected $firstDeferred;

	/**
	 * QueueFileDumper constructor.
	 * @param string $queueName
	 * @param int $messageLimit
	 * @param string $filename
	 * @param array $conditions
	 * @throws \SmashPig\Core\ConfigurationKeyException
	 */
	public function __construct( string $queueName, int $messageLimit, string $filename, array $conditions = [] ) {
		parent::__construct( $queueName, 0, $messageLimit );
		$this->file = fopen( $filename, 'a' );
		$this->conditions = $conditions;
		if ( !$this->file ) {
			throw new RuntimeException( "Can't open $filename for appending" );
		}
	}

	public function processMessage( array $message ) {
		if ( !empty( $this->conditions ) ) {
			foreach ( $this->conditions as $field => $value ) {
				if (
					empty( $message[$field] ) ||
					$message[$field] !== $value
				) {
					$this->damagedDb->storeMessage(
						$message,
						$this->queueName,
						'',
						'',
						UtcDate::getUtcTimestamp()
					);
					return;
				}
			}
		}
		fwrite( $this->file, json_encode( $message, true ) . "\n" );
	}

	public function __destruct() {
		fclose( $this->file );
	}
}
