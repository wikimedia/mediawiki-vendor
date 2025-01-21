<?php namespace SmashPig\Core\Jobs;

use SmashPig\Core\DataStores\PendingDatabase;
use SmashPig\Core\Logging\Logger;
use SmashPig\Core\Runnable;

/**
 * Job that deletes donor information from the pending database.
 * Used when we get a notification of payment failure.
 */
class DeletePendingJob implements Runnable {

	public $payload;

	/**
	 * @param string $gateway Gateway identifier
	 * @param string $orderId Payment order ID
	 * @return array
	 */
	public static function factory( string $gateway, string $orderId ): array {
		return [
			'class' => 'SmashPig\Core\Jobs\DeletePendingJob',
			'payload' => [
				'gateway' => $gateway,
				'order_id' => $orderId,
			]
		];
	}

	public function execute() {
		$gateway = $this->payload['gateway'];
		$orderId = $this->payload['order_id'];
		$logger = Logger::getTaggedLogger(
			"corr_id-{$gateway}-{$orderId}"
		);

		$logger->info(
			"Deleting message from pending db where gateway = '{$gateway}' " .
			"and order ID='{$orderId}'"
		);

		$deleteParams = [
			'gateway' => $gateway,
			'order_id' => $orderId,
		];
		PendingDatabase::get()
			->deleteMessage( $deleteParams );

		return true;
	}
}
