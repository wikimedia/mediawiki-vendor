<?php
namespace SmashPig\PaymentProviders\Gravy\Jobs;

use SmashPig\Core\DataStores\QueueWrapper;
use SmashPig\Core\Runnable;

/**
 * Job that sends a failure message to the donations queue. Only needed to make sure
 * the failure message doesn't get into the donations queue ahead of the original.
 *
 * @package SmashPig\PaymentProviders\Gravy\Jobs
 */
class RecordFailureJob implements Runnable {

	public array $payload;

	public static function factory( array $payload ): array {
		return [
			'class' => self::class,
			'payload' => $payload
		];
	}

	public function execute(): bool {
		QueueWrapper::push( 'donations', $this->payload );
		return true;
	}
}
