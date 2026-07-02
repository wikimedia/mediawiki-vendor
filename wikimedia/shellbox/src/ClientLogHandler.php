<?php
declare( strict_types = 1 );

namespace Shellbox;

use Monolog\Handler\AbstractHandler;
use Monolog\LogRecord;

class ClientLogHandler extends AbstractHandler {
	private array $records = [];

	public function handle( LogRecord $record ): bool {
		$this->records[] = [
			'level' => $record->level->getName(),
			'message' => $record->message,
			'context' => $record->context,
		];
		return false;
	}

	/**
	 * Remove and return the accumulated log entries
	 */
	public function flush(): array {
		$records = $this->records;
		$this->records = [];
		return $records;
	}
}
