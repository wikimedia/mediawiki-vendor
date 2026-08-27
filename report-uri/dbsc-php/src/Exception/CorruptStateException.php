<?php
declare(strict_types = 1);

namespace ReportUri\Dbsc\Exception;

/**
 * A stored DBSC record exists but cannot be parsed (a phpredis serializer mismatch, a truncated
 * value, or a cross-version schema skew). It is NOT client-triggerable — the write path always
 * emits valid, well-typed JSON via json_encode(..., JSON_THROW_ON_ERROR).
 *
 * Treat it as fail-closed: a present-but-unreadable binding must terminate the session, never
 * silently degrade a hard-DBSC session to plain cookie auth (that is exactly the fail-open DBSC
 * exists to prevent). Absence of a record is distinct — the store returns null for that and
 * never constructs this exception.
 */
final class CorruptStateException extends DbscException
{
}
