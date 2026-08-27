<?php
declare(strict_types = 1);

namespace ReportUri\Dbsc;

/**
 * Everything the library needs to know about the inbound request, decoupled from any HTTP
 * framework. The host application builds one of these from its own request object.
 *
 *  - `sessionId` is the host application's stable authenticated-session identifier (e.g. the
 *    session-cookie value). DBSC state is keyed by it. It MUST be the same value across the
 *    post-login navigation and the registration POST, and it MUST NOT itself live inside a
 *    read-modify-written shared session blob.
 *  - `userId` is recorded on the binding and in audit events; pass '' if not yet known.
 *  - `originHostUrl` is the scheme+host (e.g. `https://example.com`) for the DBSC scope.
 */
final class RequestContext
{

	/** @var array<string, string> */
	private readonly array $headers;

	/** @var array<string, string> */
	private readonly array $cookies;


	/**
	 * @param array<string, string> $headers request headers (any casing)
	 * @param array<string, string> $cookies request cookies
	 * @param list<string>|null $allowedRefreshInitiators per-request override for
	 *        {@see Config::$allowedRefreshInitiators}; null falls back to the Config value.
	 * @param list<ScopeRule>|null $scopeSpecification per-request override for
	 *        {@see Config::$scopeSpecification}; null falls back to the Config value, [] forces the
	 *        key off.
	 */
	public function __construct(
		public readonly string $sessionId,
		public readonly string $userId,
		public readonly string $originHostUrl,
		array $headers = [],
		array $cookies = [],
		public readonly ?array $allowedRefreshInitiators = null,
		public readonly ?array $scopeSpecification = null,
	) {
		$normalised = [];
		foreach ($headers as $name => $value) {
			$normalised[strtolower($name)] = $value;
		}
		$this->headers = $normalised;
		$this->cookies = $cookies;
	}


	public function header(string $name): ?string
	{
		return $this->headers[strtolower($name)] ?? null;
	}


	public function cookie(string $name): ?string
	{
		return $this->cookies[$name] ?? null;
	}

}
