<?php
namespace SmashPig\Maintenance;

require 'MaintenanceBase.php';

use PDO;
use SmashPig\Core\DataStores\DamagedDatabase;
use SmashPig\Core\DataStores\QueueWrapper;

/**
 * Re-queues any 'INVALID_RECURRING' donations as one-time donations
 */
class RequeueInvalidRecurring extends MaintenanceBase {

	public function __construct() {
		parent::__construct();
		$this->addOption( 'message-limit', 'number of messages to re-queue', 1 );
	}

	/**
	 * Do the actual work of the script.
	 */
	public function execute() {
		$db = DamagedDatabase::get();
		$pdo = $db->getDatabase();
		$prepared = $pdo->prepare(
			"SELECT id, message FROM `damaged` WHERE original_queue='donations' AND " .
			"error LIKE 'INVALID_RECURRING Recurring donation, but no subscription ID%' " .
			"ORDER BY id DESC LIMIT :1"
		);
		$prepared->bindValue( ':1', $this->getOption( 'message-limit' ), PDO::PARAM_INT );
		$prepared->execute();
		foreach ( $prepared->fetchAll( PDO::FETCH_ASSOC ) as $row ) {
			$message = json_decode( $row['message'], true );
			$message['recurring'] = 0;
			QueueWrapper::push( 'donations', $message );
			print 'Requeued message with ID ' . $row['id'] . ' and data ' . $row['message'] . "\n";
			$db->deleteMessage( [ 'damaged_id' => $row['id'] ] );
		}
	}
}

$maintClass = RequeueInvalidRecurring::class;

require RUN_MAINTENANCE_IF_MAIN;
