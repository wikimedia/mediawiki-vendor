<?php
declare(strict_types = 1);

namespace ReportUri\Dbsc\Exception;

/**
 * No pending challenge exists for this session (none issued, already consumed, or store lost
 * it). Benign on refresh — issue a fresh challenge and 403 so the browser retries.
 */
class MissingChallengeException extends DbscException implements RetryableRefreshException
{
}
