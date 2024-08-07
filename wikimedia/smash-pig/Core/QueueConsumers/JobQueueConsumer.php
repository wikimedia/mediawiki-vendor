<?php namespace SmashPig\Core\QueueConsumers;

use RuntimeException;
use SmashPig\Core\Logging\Logger;
use SmashPig\Core\Runnable;

class JobQueueConsumer extends BaseQueueConsumer {

	protected $successCount = 0;

	/**
	 * Instantiates and runs a job defined by a queue message. Depends on
	 * the base consumer's damaged message store functionality to either
	 * divert messages or stop execution on bad message or job failure.
	 * @param array $jobMessage
	 */
	public function processMessage( array $jobMessage ) {
		$jobObj = $this->createJobObject( $jobMessage );
		Logger::info( 'Running job' );
		if ( !$jobObj->execute() ) {
			throw new RuntimeException(
				'Job tells us that it did not successfully execute. '
				. 'Sending to damaged message store.'
			);
		}

		$this->successCount += 1;
	}

	/**
	 * @param array $jobMessage
	 * @return Runnable
	 */
	public static function createJobObject( $jobMessage ): Runnable {
		if ( isset( $jobMessage['payload'] ) && isset( $jobMessage['class'] ) ) {
			// TODO: or they could specify factory functions?
			$className = $jobMessage['class'];
			Logger::info( "Hydrating a message with class $className" );
			$job = new $className();
			$job->payload = $jobMessage['payload'];
			return $job;
		}
		throw new RuntimeException(
			'Job message needs \'class\' and \'payload\''
		);
	}

	/**
	 * @return int
	 */
	public function getSuccessCount() {
		return $this->successCount;
	}
}
