<?php
declare(strict_types = 1);

namespace ReportUri\Dbsc;

/**
 * A cookie the host application must emit. The bound DBSC cookie is independent of the
 * application session cookie and must be set/deleted explicitly. `delete = true` means emit a
 * deletion (expire in the past); `value` is ignored in that case.
 */
final class Cookie
{

	public function __construct(
		public readonly string $name,
		public readonly string $value,
		public readonly int $expiresAt,
		public readonly bool $delete = false,
		public readonly string $path = '/',
		public readonly bool $secure = true,
		public readonly bool $httpOnly = true,
		public readonly string $sameSite = 'Lax',
	) {
	}


	public static function deletion(string $name, string $path = '/'): self
	{
		return new self($name, '', 0, true, $path);
	}

}
