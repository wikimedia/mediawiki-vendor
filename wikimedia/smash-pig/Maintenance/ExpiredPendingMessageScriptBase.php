<?php
namespace SmashPig\Maintenance;

require 'MaintenanceBase.php';

use SmashPig\Core\DataStores\PendingDatabase;
use SmashPig\Core\Logging\Logger;
use SmashPig\Core\UtcDate;

/**
 * Base class for scripts that acts on old messages from the pending table
 */
abstract class ExpiredPendingMessageScriptBase extends MaintenanceBase {

	public function __construct() {
		parent::__construct();
		$this->addOption( 'gateway', 'gateway to target' );
		$this->addOption( 'days', 'age in days of oldest messages to leave alone', 30 );
		$this->addOption( 'hours', 'age in hours of oldest messages to leave alone' );
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
		$affected = $this->doTheThing( $pendingDatabase, $deleteBefore, $gateway );

		$elapsedTime = time() - $startTime;
		Logger::info(
			"Affected $affected pending messages in $elapsedTime seconds."
		);
	}

	abstract protected function doTheThing( PendingDatabase $pendingDatabase, string $deleteBefore, ?string $gateway ): int;
}
