<?php
namespace SmashPig\Maintenance;

require 'MaintenanceBase.php';

use SmashPig\Core\DataStores\PendingDatabase;
use SmashPig\Core\Logging\Logger;
use SmashPig\Core\UtcDate;

/**
 * Deletes old messages from the pending table
 */
class DeleteExpiredPendingMessages extends MaintenanceBase {

	public function __construct() {
		parent::__construct();
		$this->addOption( 'gateway', 'gateway to delete messages for' );
		$this->addOption( 'days', 'age in days of oldest messages to keep', 30 );
		$this->addOption( 'hours', 'age in hours of oldest messages to keep' );
	}

	/**
	 * Do the actual work of the script.
	 */
	public function execute() {
		$pendingDatabase = PendingDatabase::get();
		$gateway = $this->getOption( 'gateway' );

		if ( $this->optionProvided( 'hours' ) ) {
			$hours = $this->getOption( 'hours' );
			$deleteBefore = UtcDate::getUtcTimestamp( "-$hours hours" );
		} else {
			$days = $this->getOption( 'days' );
			$deleteBefore = UtcDate::getUtcTimestamp( "-$days days" );
		}

		$startTime = time();
		$deleted = $pendingDatabase->deleteOldMessages( $deleteBefore, $gateway );

		$elapsedTime = time() - $startTime;
		Logger::info(
			"Deleted $deleted pending messages in $elapsedTime seconds."
		);
	}
}

$maintClass = DeleteExpiredPendingMessages::class;

require RUN_MAINTENANCE_IF_MAIN;
