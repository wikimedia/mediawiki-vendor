<?php namespace SmashPig\Tests;

use SmashPig\Core\QueueConsumers\BaseQueueConsumer;

class TestingQueueConsumer extends BaseQueueConsumer {

	public $exception;
	public $processed = [];

	public function processMessage( array $message ) {
		$this->processed[] = $message;
		if ( $this->exception ) {
			throw $this->exception;
		}
	}
}
