<?php
declare(strict_types = 1);

namespace ReportUri\Dbsc\Exception;

/**
 * The refresh JWT's signature verified against the device key, but its `jti` matched neither the
 * current nor the previous challenge. A validly-signed JWT proves the device-bound private key
 * was used, so this can only be a benign race (idle session, concurrent refresh, lost 403) —
 * unlike {@see JwtInvalidException}, NOT a stolen-cookie signal. Benign on refresh: issue a
 * fresh challenge and 403 so the browser retries.
 */
class ChallengeMismatchException extends DbscException implements RetryableRefreshException
{
}
