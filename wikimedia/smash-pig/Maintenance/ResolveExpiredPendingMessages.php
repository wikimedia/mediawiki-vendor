<?php
namespace SmashPig\Maintenance;

require 'ExpiredPendingMessageScriptBase.php';

use SmashPig\Core\DataStores\PendingDatabase;

/**
 * Marks older messages from the pending table as resolved
 */
class ResolveExpiredPendingMessages extends ExpiredPendingMessageScriptBase {

	protected function doTheThing( PendingDatabase $pendingDatabase, string $deleteBefore, ?string $gateway ): int {
		return $pendingDatabase->resolveOldMessages( $deleteBefore, $gateway );
	}
}

$maintClass = ResolveExpiredPendingMessages::class;

require RUN_MAINTENANCE_IF_MAIN;
