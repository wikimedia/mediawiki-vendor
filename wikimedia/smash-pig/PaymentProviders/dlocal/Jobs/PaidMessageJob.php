<?php namespace SmashPig\PaymentProviders\dlocal\Jobs;

use SmashPig\Core\DataStores\PendingDatabase;
use SmashPig\Core\DataStores\QueueWrapper;
use SmashPig\Core\Logging\Logger;
use SmashPig\Core\Runnable;

/**
 * Job that merges a PAID IPN message from dlocal with donor info from the
 * pending database, then places that into the donations queue for one time
 * donations and onto the upi-donations queue for recurring donations.
 *
 * Class PaidMessageJob
 *
 * @package SmashPig\PaymentProviders\dlocal\Jobs
 */
class PaidMessageJob implements Runnable {

	public $payload;

	public function execute() {
		$logger = Logger::getTaggedLogger( "corr_id-dlocal-{$this->payload['gateway_txn_id']}" );
		$logger->info(
			"Recording PAID status on " .
			"'{$this->payload['gateway_txn_id']}' and order ID '{$this->payload['order_id']}'."
		);

		// See if its in the pending database, if it is combine the pending info and ipn info
		$logger->debug( 'Attempting to locate associated message in pending database' );
		$db = PendingDatabase::get();
		$dbMessage = $db->fetchMessageByGatewayOrderId( 'dlocal', $this->payload['order_id'] );

		if ( $dbMessage ) {
			$logger->debug( 'A valid message was obtained from the pending queue' );

			// Combine pending and ipn info
			$message = array_merge( $dbMessage, $this->payload );

			// Remove it from the pending database
			$logger->debug( 'Removing donor details message from pending database' );
			$db->deleteMessage( $dbMessage );

		} else {
			// If its not in the pending queue, still send it to the queues
			// Subsequent recurrings won't have pending entries
			$logger->debug(
				"Could not find donor details in pending for dlocal '{$this->payload['gateway_txn_id']}' " .
					"and order ID '{$this->payload['order_id']}'."
			);

			$message = $this->payload;
		}

		// Remove unneeded values from the final queue message
		unset( $message['dlocal_payment_method'] );
		unset( $message['pending_id'] );

		if ( $this->payload['dlocal_payment_method'] == 'IR' ) {
			// This is a recurring payment, put it on the India recurring queue
			QueueWrapper::push( 'upi-donations', $message );
		} else {
			// One time donation, put it on the donations queue
			QueueWrapper::push( 'donations', $message );
		}

		return true;
	}
}
