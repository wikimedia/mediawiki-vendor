<?php
declare(strict_types = 1);

namespace ReportUri\Dbsc;

final class NullAuditLogger implements AuditLoggerInterface
{

	public function log(string $event, string $message, ?string $userId): void
	{
	}

}
