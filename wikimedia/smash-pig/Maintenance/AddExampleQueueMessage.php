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
		} elseif ( $message == 'sms-optin' ) {
			// AddExampleQueueMessage.php --message sms-optin
			// Gravy adyen example
			$queue = 'donations';
			$random = rand( 200, 20000 );
			$example['gateway_txn_id'] = '1234-ABCD-5678-EFGH-' . $random;
			$example['response'] = false;
			$example['gateway_account'] = 'Test';
			$example['fee'] = 0;
			$example['gross'] = "12.34";
			$example['backend_processor'] = 'adyen';
			$example['backend_processor_txn_id'] = $random . 'ABBCD';
			$example['contribution_tracking_id'] = $random;
			$example['country'] = 'US';
			$example['city'] = 'Denver';
			$example['postal_code'] = '80202';
			$example['state_province'] = 'CO';
			$example['street_address'] = '1234 Street St';
			$example['currency'] = 'USD';
			$example['email'] = 'newcontact@test' . $random . '.com';
			$example['first_name'] = 'Phone';
			$example['gateway'] = 'gravy';
			$example['language'] = 'en';
			$example['last_name'] = 'Name';
			$example['order_id'] = $random . '.1';
			$example['payment_method'] = 'cc';
			$example['payment_submethod'] = 'visa';
			$example['payment_orchestrator_reconciliation_id'] = '12345ABCD';
			$example['phone'] = '1' . rand( 200, 999 ) . '-' . rand( 100, 999 ) . '-' . rand( 1000, 9999 );
			$example['sms_opt_in'] = 1;
			$example['date'] = time();
		}

		QueueWrapper::push( $queue, $example );
	}
}

$maintClass = AddExampleQueueMessage::class;

require RUN_MAINTENANCE_IF_MAIN;
