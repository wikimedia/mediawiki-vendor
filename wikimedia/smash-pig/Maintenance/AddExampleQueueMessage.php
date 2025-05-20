<?php namespace SmashPig\Maintenance;

require 'MaintenanceBase.php';

use SmashPig\Core\DataStores\QueueWrapper;

/**
 * Puts an example message onto the queue
 *
 * @package SmashPig\Maintenance
 */
class AddExampleQueueMessage extends MaintenanceBase {

	public function __construct() {
		parent::__construct();
		$this->addOption( 'message', 'what queue message to add', 'test' );
	}

	/**
	 * Do the actual work of the script.
	 */
	public function execute() {
		$message = $this->getOption( 'message' );

		// AddExampleQueueMessage.php --message autorescue-failure
		if ( $message == 'autorescue-failure' ) {
			$queue = 'recurring';
			$example['txn_type'] = "subscr_cancel";
			// Set civicrm_contribution_recur_smashpig.rescue_reference to this
			$example['rescue_reference'] = "ZJ2HKCRVMB383Z59";
			$example['is_autorescue'] = 'true';
			$example['cancel_reason'] = 'Payment cannot be rescued: maximum failures reached';
		}

		QueueWrapper::push( $queue, $example );
	}
}

$maintClass = AddExampleQueueMessage::class;

require RUN_MAINTENANCE_IF_MAIN;
