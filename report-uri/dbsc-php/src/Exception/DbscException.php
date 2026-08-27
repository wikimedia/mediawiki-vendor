<?php
declare(strict_types = 1);

namespace ReportUri\Dbsc\Exception;

/**
 * Base type for every recoverable DBSC protocol failure. Catch this in the refresh handler to
 * treat any verification failure as "terminate the session" (see README, refresh flow).
 */
class DbscException extends \Exception
{
}
