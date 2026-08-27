<?php
declare(strict_types = 1);

namespace ReportUri\Dbsc\Exception;

/**
 * No binding exists for this session, or the presented Sec-Secure-Session-Id does not match the
 * stored one. On refresh: terminate the session.
 */
class SessionNotFoundException extends DbscException
{
}
