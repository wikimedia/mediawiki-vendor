<?php namespace SmashPig\Core\QueueConsumers;

use RuntimeException;
use SmashPig\Core\DataStores\JsonSerializableObject;
use SmashPig\Core\Logging\Logger;
use SmashPig\Core\Runnable;

class JobQueueConsumer extends BaseQueueConsumer {

	protected $successCount = 0;

	/**
	 * Instantiates and runs a job defined by a queue message. Depends on
	 * the base consumer's damaged message store functionality to either
	 * divert messages or stop execution on bad message or job failure.
	 * @param array $jobMessage
	 * @throws \SmashPig\Core\DataStores\DataSerializationException
	 */
	public function processMessage( array $jobMessage ) {
		$jobObj = $this->createJobObject( $jobMessage );
		if ( $jobObj instanceof Runnable ) {
			Logger::info( 'Running job' );
			if ( !$jobObj->execute() ) {
				throw new RuntimeException(
					'Job tells us that it did not successfully execute. '
					. 'Sending to damaged message store.'
				);
			}
		} else {
			// We don't know how to run this job type.
			throw new RuntimeException(
				get_class( $jobObj ) . ' is not an instance of Runnable. '
				. 'Could not execute and sending to damaged message store.'
			);
		}

		$this->successCount += 1;
	}

	/**
	 * @param array $jobMessage
	 * @return JsonSerializableObject|Runnable
	 * @throws \SmashPig\Core\DataStores\DataSerializationException
	 */
	public static function createJobObject( $jobMessage ) {
		// older, deprecated jobs
		if ( isset( $jobMessage['php-message-class'] ) ) {
			// FIXME It seems bad that these objects indiscriminately store
			// things as properties. The message is mingled with stuff like
			// php-message-class. Could collide.
			$className = $jobMessage['php-message-class'];
			Logger::info( "Hydrating a message with class $className" );
			$jsonMessage = json_encode( $jobMessage );
			Logger::debug( "Job payload: $jsonMessage" );
			return JsonSerializableObject::fromJsonProxy( $className, $jsonMessage );
		} elseif ( isset( $jobMessage['payload'] ) && isset( $jobMessage['class'] ) ) {
			// TODO: or they could specify factory functions?
			$className = $jobMessage['class'];
			Logger::info( "Hydrating a message with class $className" );
			$job = new $className();
			$job->payload = $jobMessage['payload'];
			return $job;
		} else {
			throw new RuntimeException(
				'Job message needs either \'php-message-class\' or \'class\' and \'payload\''
			);
		}
	}

	/**
	 * @return int
	 */
	public function getSuccessCount() {
		return $this->successCount;
	}
}
