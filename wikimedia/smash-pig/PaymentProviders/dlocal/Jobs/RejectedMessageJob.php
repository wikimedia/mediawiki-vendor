<?php

namespace SmashPig\PaymentProviders\dlocal\Jobs;

use SmashPig\Core\DataStores\QueueWrapper;
use SmashPig\Core\Logging\Logger;
use SmashPig\Core\Runnable;

/**
 * Job to decide whether the REJECTION message needs processing or not.
 *
 * If a 'Wallet disabled' rejection is detected, we push it to the 'upi-donations'
 * queue so that the UpiQueueConsumer can close it down. See T341300
 */
class RejectedMessageJob implements Runnable {

	public const WALLET_DISABLED_STATUS = 322;

	public $payload;

	public function execute() {
		$logger = Logger::getTaggedLogger( "dlocal-{$this->payload['gateway_txn_id']}" );
		if ( $this->isDLocalWalletDisabledRejection() ) {
			$logger->debug( 'dLocal Wallet disabled rejection detected. Pushing to upi-donations queue' );
			QueueWrapper::push( 'upi-donations', $this->payload );
		} else {
			$logger->debug( 'dLocal general rejection message detected. No action needed' );
		}

		return true;
	}

	/**
	 * @return bool
	 */
	protected function isDLocalWalletDisabledRejection(): bool {
		return $this->payload['gateway_status'] === 'REJECTED' && (int)$this->payload['gateway_status_code'] === self::WALLET_DISABLED_STATUS;
	}

}
