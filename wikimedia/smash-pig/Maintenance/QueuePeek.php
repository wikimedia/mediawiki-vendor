<?php namespace SmashPig\Maintenance;

require 'MaintenanceBase.php';

use SmashPig\Core\DataStores\QueueWrapper;

/**
 * Script to display what is in the queue in a friendly way.
 *
 * @package SmashPig\Maintenance
 */
class QueuePeek extends MaintenanceBase {

	public function __construct() {
		parent::__construct();
		$this->addArgument( 'queue', 'queue name to consume from', true );
	}

	/**
	 * Do the actual work of the script.
	 */
	public function execute() {
		$queueName = $this->getArgument( 'queue' );
		$queue = QueueWrapper::getQueue( $queueName );
		$msg = $queue->peek();
		print_r( $msg );
	}

}

$maintClass = QueuePeek::class;

require RUN_MAINTENANCE_IF_MAIN;
