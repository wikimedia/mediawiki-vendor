<?php namespace SmashPig\Maintenance;

require 'MaintenanceBase.php';

use SmashPig\Core\Context;
use SmashPig\Core\Logging\Logger;
use SmashPig\Core\QueueConsumers\JobQueueConsumer;

/**
 * The job runner script reads job requests from a specified queue and dispatches the requests.
 * It attempts to time limit itself; however a long running job will not be terminated. Rather
 * after it completes no new jobs will be dispatched and this script will exit.
 */
class QueueJobRunner extends MaintenanceBase {

	public function __construct() {
		parent::__construct();

		$this->addOption( 'queue', 'queue name to consume from', 'jobs' );
		$this->addOption( 'time-limit', 'Try to keep execution under <n> seconds', 60, 't' );
		$this->addOption( 'max-messages', 'At most consume <n> messages', 10, 'm' );
	}

	/**
	 * Do the actual work of the script.
	 */
	public function execute() {
		Context::get()->setSourceType( 'job-runner' );
		// Get some defaults from configuration
		$basePath = 'maintenance/job-runner/';

		$consumer = new JobQueueConsumer(
			$this->getOption( 'queue' ),
			$this->getOptionOrConfig( 'time-limit', $basePath . 'time-limit' ),
			$this->getOptionOrConfig( 'max-messages', $basePath . 'message-limit' )
		);

		$startTime = time();
		$messageCount = $consumer->dequeueMessages();

		$successCount = $consumer->getSuccessCount();
		$elapsedTime = time() - $startTime;
		Logger::info(
			"Processed $messageCount ($successCount successful) jobs in $elapsedTime seconds."
		);
	}

}

$maintClass = QueueJobRunner::class;

require RUN_MAINTENANCE_IF_MAIN;
