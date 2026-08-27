<?php
declare(strict_types = 1);

namespace ReportUri\Dbsc;

/**
 * Optional hook for recording DBSC state transitions in the host application's audit trail.
 * Every transition is reported: dbscRegistered, dbscRegistrationFailed, dbscRefreshed,
 * dbscRefreshFailed, dbscRevoked, dbscEnforcementTerminated. Pass {@see NullAuditLogger} to
 * ignore them.
 */
interface AuditLoggerInterface
{

	public function log(string $event, string $message, ?string $userId): void;

}
