<?php
namespace SmashPig\Maintenance;

require 'MaintenanceBase.php';

use SmashPig\Core\Logging\Logger;
use SmashPig\Core\QueueConsumers\PendingQueueConsumer;

/**
 * Reads messages out of the pending queue and inserts them into a db table
 */
class ConsumePendingQueue extends MaintenanceBase {

	public function __construct() {
		parent::__construct();
		$this->addOption( 'queue', 'queue name to consume from', 'pending' );
		$this->addOption( 'time-limit', 'Try to keep execution under <n> seconds', 60, 't' );
		$this->addOption( 'max-messages', 'At most consume <n> messages', 0, 'm' );
		$this->addFlag( 'wait-for-messages', 'Wait for messages when queue is empty', 'w' );
	}

	/**
	 * Do the actual work of the script.
	 */
	public function execute() {
		$basePath = 'maintenance/consume-pending/';
		$consumer = new PendingQueueConsumer(
			$this->getOption( 'queue' ),
			$this->getOptionOrConfig( 'time-limit', $basePath . 'time-limit' ),
			$this->getOptionOrConfig( 'max-messages', $basePath . 'message-limit' ),
			$this->getOptionOrConfig( 'wait-for-messages', $basePath . 'wait-for-messages' )
		);

		$startTime = time();
		$messageCount = $consumer->dequeueMessages();

		$elapsedTime = time() - $startTime;
		Logger::info(
			"Processed $messageCount pending messages in $elapsedTime seconds."
		);
	}
}

$maintClass = ConsumePendingQueue::class;

require RUN_MAINTENANCE_IF_MAIN;
