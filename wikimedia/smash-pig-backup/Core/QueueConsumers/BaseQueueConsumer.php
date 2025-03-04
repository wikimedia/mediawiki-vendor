<?php
namespace SmashPig\Core\QueueConsumers;

use Exception;
use InvalidArgumentException;
use PHPQueue\Exception\JsonException;
use PHPQueue\Interfaces\AtomicReadBuffer;

use SmashPig\Core\Context;
use SmashPig\Core\DataStores\DamagedDatabase;
use SmashPig\Core\DataStores\QueueWrapper;
use SmashPig\Core\Logging\Logger;
use SmashPig\Core\RetryableException;
use SmashPig\Core\UtcDate;

/**
 * Facilitates guaranteed message processing using PHPQueue's AtomicReadBuffer
 * interface. Exceptions in the processing callback will cause the message to
 * be sent to a damaged message datastore.
 */
abstract class BaseQueueConsumer {

	/**
	 * @var AtomicReadBuffer
	 */
	protected $backend;

	protected $queueName;

	/**
	 * @var callable
	 */
	protected $callback;

	/**
	 * @var DamagedDatabase
	 */
	protected $damagedDb;

	protected $timeLimit = 0;

	protected $waitForNewMessages = false;

	protected $messageLimit = 0;

	/**
	 * Do something with the message popped from the queue. Return value is
	 * ignored, and exceptions will be caught and handled by handleError.
	 *
	 * @param array $message
	 */
	abstract public function processMessage( array $message );

	/**
	 * Gets a fresh QueueConsumer
	 *
	 * @param string $queueName key of queue configured in data-store, must
	 *  implement @see PHPQueue\Interfaces\AtomicReadBuffer.
	 * @param int $timeLimit max number of seconds to loop, 0 for no limit
	 * @param int $messageLimit max number of messages to process, 0 for all
	 * @param bool $waitForNewMessages when true, sleep a second on empty queue
	 * @throws \SmashPig\Core\ConfigurationKeyException
	 */
	public function __construct(
		string $queueName,
		int $timeLimit = 0,
		int $messageLimit = 0,
		bool $waitForNewMessages = false
	) {
		if ( $waitForNewMessages && $timeLimit === 0 && $messageLimit === 0 ) {
			throw new InvalidArgumentException(
				'Waiting for new messages requires either a message or time limit'
			);
		}

		$this->queueName = $queueName;
		$this->timeLimit = $timeLimit;
		$this->waitForNewMessages = $waitForNewMessages;
		$this->messageLimit = $messageLimit;

		$this->backend = QueueWrapper::getQueue( $queueName );

		if ( !$this->backend instanceof AtomicReadBuffer ) {
			throw new InvalidArgumentException(
				"Queue $queueName is not an AtomicReadBuffer"
			);
		}

		$this->damagedDb = DamagedDatabase::get();
	}

	/**
	 * Dequeue and process messages until time limit or message limit is
	 * reached, or till queue is empty. When configured to wait for new
	 * messages, sleeps for a second instead of quitting on empty queue.
	 *
	 * @return int number of messages processed
	 * @throws Exception
	 */
	public function dequeueMessages(): int {
		$startTime = time();
		$processed = 0;
		$realCallback = [ $this, 'processMessageWithErrorHandling' ];
		do {
			try {
				$data = $this->backend->popAtomic( $realCallback );
				if ( $data !== null ) {
					$processed++;
				}
			} catch ( JsonException $ex ) {
				// Set a non-null value so as not to exit the loop
				$data = false;
				$this->sendToDamagedStore( [], $ex );
			}
			$timeOk = $this->timeLimit === 0 || time() <= $startTime + $this->timeLimit;
			$countOk = $this->messageLimit === 0 || $processed < $this->messageLimit;

			$debugMessages = [];
			if ( $data === null ) {
				if ( $this->waitForNewMessages && $timeOk ) {
					sleep( 1 );
					// Set a non-null value so as not to exit the loop
					$data = false;
				} else {
					$debugMessages[] = 'Queue is empty.';
				}
			}
			if ( !$timeOk ) {
				$debugMessages[] = "Time limit ($this->timeLimit) is elapsed.";
			}
			if ( !$countOk ) {
				$debugMessages[] = "Message limit ($this->messageLimit) is reached.";
			}
			if ( !empty( $debugMessages ) ) {
				Logger::debug( implode( ' ', $debugMessages ) );
			}
		}
		while ( $timeOk && $countOk && $data !== null );
		return $processed;
	}

	/**
	 * Call the concrete processMessage function and handle any errors that
	 * may arise.
	 *
	 * @param array $message
	 */
	public function processMessageWithErrorHandling( array $message ) {
		try {
			$this->processMessage( $message );
		} catch ( Exception $ex ) {
			$this->handleError( $message, $ex );
		}
	}

	/**
	 * Using an AtomicReadBuffer implementation for the backend means that
	 * if this throws an exception, the message will remain on the queue.
	 *
	 * @param array $message
	 * @param Exception $ex
	 * @throws \SmashPig\Core\ConfigurationKeyException
	 */
	protected function handleError( array $message, Exception $ex ) {
		if ( $ex instanceof RetryableException ) {
			$now = UtcDate::getUtcTimestamp();

			if ( !isset( $message['source_enqueued_time'] ) ) {
				$message['source_enqueued_time'] = UtcDate::getUtcTimestamp();
			}
			$expirationDate = $message['source_enqueued_time'] +
				$this->getRequeueMaxAge();

			if ( $now < $expirationDate ) {
				$retryDate = $now + $this->getRequeueDelay();
				$this->sendToDamagedStore( $message, $ex, $retryDate );
				return;
			}
		}
		$this->sendToDamagedStore( $message, $ex );
	}

	/**
	 * @param array $message The data
	 * @param Exception $ex The problem
	 * @param int|null $retryDate If provided, retry after this timestamp
	 * @return int ID of message in damaged database
	 * @throws \SmashPig\Core\DataStores\DataStoreException
	 */
	protected function sendToDamagedStore(
		array $message, Exception $ex, $retryDate = null
	) {
		if ( $retryDate ) {
			Logger::notice(
				'Message not fully baked. Sticking it back in the oven, to ' .
				"retry at $retryDate",
				$message
			);
		} else {
			Logger::error(
				'Error processing message, moving to damaged store.',
				$message,
				$ex
			);
		}
		return $this->damagedDb->storeMessage(
			$message,
			$this->queueName,
			$ex->getMessage(),
			$ex->getTraceAsString(),
			$retryDate
		);
	}

	protected function getRequeueDelay() {
		return Context::get()->getGlobalConfiguration()->val( 'requeue-delay' );
	}

	protected function getRequeueMaxAge() {
		return Context::get()->getGlobalConfiguration()->val( 'requeue-max-age' );
	}
}
