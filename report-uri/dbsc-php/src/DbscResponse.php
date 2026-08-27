<?php
declare(strict_types = 1);

namespace ReportUri\Dbsc;

/**
 * What the host application must emit in response to a DBSC operation: headers, cookies, and —
 * for endpoint responses — an HTTP status and body. `status` is null for operations that only
 * decorate an existing response (the login registration header), so the caller leaves its own
 * status untouched.
 *
 * The DBSC endpoint responses intentionally set `Content-Type: application/json` even when the
 * body is empty (the 403 challenge), so a framework debug bar / error page is not injected into
 * a response the browser parses strictly.
 */
final class DbscResponse
{

	/**
	 * @param array<string, string> $headers
	 * @param list<Cookie> $cookies
	 */
	public function __construct(
		public readonly array $headers = [],
		public readonly array $cookies = [],
		public readonly ?int $status = null,
		public readonly ?string $body = null,
		public readonly ?string $contentType = null,
	) {
	}

}
