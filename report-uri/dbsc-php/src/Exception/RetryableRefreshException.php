<?php
declare(strict_types = 1);

namespace ReportUri\Dbsc\Exception;

/**
 * Marker for benign refresh() failures — issue a fresh challenge and 403 instead of terminating.
 * Implemented by {@see MissingChallengeException}, {@see ChallengeExpiredException}, and
 * {@see ChallengeMismatchException}; catch this instead of enumerating all three.
 */
interface RetryableRefreshException
{
}
