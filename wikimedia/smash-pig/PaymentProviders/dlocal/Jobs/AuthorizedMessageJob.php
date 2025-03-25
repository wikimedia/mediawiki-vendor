<?php namespace SmashPig\PaymentProviders\dlocal\Jobs;

use SmashPig\Core\DataStores\PendingDatabase;
use SmashPig\Core\Logging\Logger;
use SmashPig\Core\Runnable;

/**
 * Job that adds token information from an Authorized IPN message from dlocal
 * with donor info from the pending database, then drops it back into the pending DB
 */
class AuthorizedMessageJob implements Runnable {

	public $payload;

	public function execute() {
		$logger = Logger::getTaggedLogger( "corr_id-dlocal-{$this->payload['gateway_txn_id']}" );

		if ( empty( $this->payload['recurring_payment_token'] ) ) {
			return true;
		}
		$logger->info(
			"Found token from AUTHORIZED status message on " .
			"'{$this->payload['gateway_txn_id']}' and order ID '{$this->payload['order_id']}'."
		);

		// See if it's in the pending database, if it is combine the pending info and ipn info
		$logger->debug( 'Attempting to locate associated message in pending database' );
		$db = PendingDatabase::get();
		$dbMessage = $db->fetchMessageByGatewayOrderId( 'dlocal', $this->payload['order_id'] );

		if ( $dbMessage ) {
			$logger->debug( 'A valid message was obtained from the pending queue' );

			if ( empty( $dbMessage['recurring_payment_token'] ) ) {
				// Combine pending and ipn info
				$dbMessage['recurring_payment_token'] = $this->payload['recurring_payment_token'];

				// Save it back to the pending database
				$logger->debug( 'Updating message in pending database' );
				$db->storeMessage( $dbMessage );
			} else {
				$logger->debug( 'Pending row already has recurring token' );
			}
		} else {
			$logger->debug(
				"Could not find donor details in pending for dlocal '{$this->payload['gateway_txn_id']}' " .
					"and order ID '{$this->payload['order_id']}'."
			);
		}
		return true;
	}
}
