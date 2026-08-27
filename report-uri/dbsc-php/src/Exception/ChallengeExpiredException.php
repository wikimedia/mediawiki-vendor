<?php
declare(strict_types = 1);

namespace ReportUri\Dbsc\Exception;

/**
 * The server challenge the browser signed has exceeded its TTL. Benign on refresh — issue a
 * fresh challenge and 403 so the browser retries (see README, refresh flow).
 */
class ChallengeExpiredException extends DbscException implements RetryableRefreshException
{
}
