<?php
declare(strict_types = 1);

namespace ReportUri\Dbsc\Exception;

/**
 * The presented JWT is structurally invalid, uses an unsupported algorithm, or its signature
 * does not verify against the device key. On refresh this is the stolen-cookie-from-another-
 * device signal: terminate the session.
 */
class JwtInvalidException extends DbscException
{
}
