<?php
declare(strict_types = 1);

namespace ReportUri\Dbsc;

/**
 * One rule in a session's `scope_specification` — a modification to the default scope, either
 * pulling something in or pushing something out.
 *
 * WHY THIS MATTERS MORE THAN IT LOOKS. A session's scope decides which requests the browser will
 * DEFER while it refreshes an expired bound cookie, and every refresh spends a signature from a
 * rate-limited device key. Leave the default whole-origin scope in place and your static assets
 * are in it: a cold page load with an expired cookie fires every stylesheet, script and icon at
 * once, and the browser attempts a separate refresh for each — it does not coalesce them. Measured
 * against Chrome 151, seven assets produced seven signing attempts in the same second, which is
 * enough to exhaust the quota. Once exhausted the session wedges: refreshes stop, the browser will
 * not register a replacement for a scope it already covers, and the site becomes indistinguishable
 * from one the browser has no DBSC support for.
 *
 * So the rule of thumb is: scope the session to the authenticated surface it protects, not to the
 * whole origin. Anything that needs no session — assets, health checks, status polling — should be
 * excluded, because including it buys nothing and costs a signature per request.
 *
 * @see https://w3c.github.io/webappsec-dbsc/#scope-specification
 */
final class ScopeRule
{

	public const TYPE_INCLUDE = 'include';
	public const TYPE_EXCLUDE = 'exclude';


	/**
	 * @param string $type self::TYPE_INCLUDE or self::TYPE_EXCLUDE
	 * @param string|null $domain host pattern; null means the session's own host
	 * @param string|null $path path PREFIX, not an exact match — '/assets/' covers everything under it
	 */
	private function __construct(
		public readonly string $type,
		public readonly ?string $domain = null,
		public readonly ?string $path = null,
	) {
		if ($type !== self::TYPE_INCLUDE && $type !== self::TYPE_EXCLUDE) {
			throw new \InvalidArgumentException('DBSC: scope rule type must be "include" or "exclude".');
		}
		if ($domain === null && $path === null) {
			throw new \InvalidArgumentException('DBSC: a scope rule needs a domain, a path, or both.');
		}
	}


	/** Bring something into scope that the default scope leaves out. */
	public static function include(?string $path = null, ?string $domain = null): self
	{
		return new self(self::TYPE_INCLUDE, $domain, $path);
	}


	/** Take something out of scope. This is the one you almost always want. */
	public static function exclude(?string $path = null, ?string $domain = null): self
	{
		return new self(self::TYPE_EXCLUDE, $domain, $path);
	}


	/**
	 * The wire form. Absent members are omitted rather than sent as null — the spec treats a missing
	 * domain or path as "unconstrained", which is not the same as an explicit null.
	 *
	 * @return array<string, string>
	 */
	public function toArray(): array
	{
		$rule = ['type' => $this->type];
		if ($this->domain !== null) {
			$rule['domain'] = $this->domain;
		}
		if ($this->path !== null) {
			$rule['path'] = $this->path;
		}
		return $rule;
	}

}
