<?php
declare(strict_types = 1);

namespace ReportUri\Dbsc;

use ReportUri\Dbsc\Exception\ChallengeExpiredException;
use ReportUri\Dbsc\Exception\ChallengeMismatchException;
use ReportUri\Dbsc\Exception\CorruptStateException;
use ReportUri\Dbsc\Exception\JwtInvalidException;
use ReportUri\Dbsc\Exception\MissingChallengeException;
use ReportUri\Dbsc\Exception\SessionNotFoundException;

/**
 * Framework-agnostic DBSC server. Every method takes a {@see RequestContext} the host built
 * from its own request and returns a {@see DbscResponse} the host applies to its own response —
 * the library never touches PHP superglobals, sends headers, or sets cookies itself.
 *
 * DBSC state lives in the injected {@see StoreInterface}, keyed by the host's session id. It is
 * deliberately NOT in a shared session blob: the blob is read-modify-written by every request,
 * so the post-login navigation racing the registration POST would clobber the binding and
 * silently disable enforcement (a stolen cookie would then still work — exactly what DBSC
 * exists to prevent). Keying by the stable session id removes that shared cell entirely.
 *
 * Two store keys, deliberately separate (see {@see StoreInterface}): a pending registration is
 * written when DBSC is *offered* at login; a binding is written only on a *successful*
 * registration and its existence is the authoritative "this session is hard-DBSC" mark.
 */
final class DbscServer
{

	public const EVENT_REGISTERED = 'dbscRegistered';
	public const EVENT_REGISTRATION_FAILED = 'dbscRegistrationFailed';
	public const EVENT_REFRESHED = 'dbscRefreshed';
	public const EVENT_REFRESH_FAILED = 'dbscRefreshFailed';
	public const EVENT_REFRESH_RETRYABLE = 'dbscRefreshRetryable';
	public const EVENT_REVOKED = 'dbscRevoked';
	public const EVENT_ENFORCEMENT_TERMINATED = 'dbscEnforcementTerminated';

	private const ALGORITHM = 'ES256';
	private const NONCE_BYTES = 32;
	private const SESSION_ID_BYTES = 32;
	private const COOKIE_VALUE_BYTES = 32;

	private readonly AuditLoggerInterface $audit;


	public function __construct(
		private readonly Config $config,
		private readonly StoreInterface $store,
		private readonly JwtVerifier $jwtVerifier = new JwtVerifier(),
		?AuditLoggerInterface $audit = null,
	) {
		$this->audit = $audit ?? new NullAuditLogger();
	}


	/**
	 * Call after full authentication (post-2FA / post-passkey). Returns a response carrying the
	 * Secure-Session-Registration header, telling the browser to generate a device-bound key and
	 * POST a registration JWT. The challenge is stored as a pending registration (never the
	 * binding key) and echoed back as the JWT `jti`. `status` is null — merge these headers into
	 * the normal login response. A non-DBSC browser ignores the header and never registers, so
	 * the binding is never created and the gate degrades to plain cookie auth: locking such a
	 * user out is structurally impossible.
	 */
	public function buildRegistrationHeaderResponse(RequestContext $ctx): DbscResponse
	{
		$challenge = $this->nonce();
		$this->store->putPendingRegistration(
			$ctx->sessionId,
			new PendingRegistration($ctx->userId, $challenge, time()),
		);

		$value = sprintf('(%s); path="%s"; challenge="%s"', self::ALGORITHM, $this->config->registerPath, $challenge);
		return new DbscResponse(headers: ['Secure-Session-Registration' => $value]);
	}


	/**
	 * Verify a registration JWT (single-phase, per the W3C spec). On success creates the binding
	 * — the authoritative hard-DBSC mark — and returns 200 with the bound cookie, the
	 * Sec-Secure-Session-Id header, and the session-instructions JSON body.
	 *
	 * Only Sec-Secure-Session-Id is emitted here: a Secure-Session-Challenge on the registration
	 * response makes Chrome report a Challenge Error. The challenge belongs to the refresh flow
	 * and is issued via the first /dbsc/refresh 403. The binding carries an internally-seeded
	 * challenge purely so it stays valid until that first rotation.
	 *
	 * @throws JwtInvalidException
	 * @throws ChallengeExpiredException
	 * @throws MissingChallengeException
	 * @throws CorruptStateException if a pending-registration record exists but is unreadable
	 */
	public function register(string $jwt, RequestContext $ctx): DbscResponse
	{
		$pending = $this->store->getPendingRegistration($ctx->sessionId);
		if (!$pending instanceof PendingRegistration) {
			$this->fail(self::EVENT_REGISTRATION_FAILED, 'No pending registration challenge.', $ctx);
			throw new MissingChallengeException('No pending registration challenge');
		}
		$this->store->deletePendingRegistration($ctx->sessionId);
		if (time() - $pending->regChallengeTime > $this->config->challengeTtlSeconds) {
			$this->fail(self::EVENT_REGISTRATION_FAILED, 'Registration challenge expired.', $ctx);
			throw new ChallengeExpiredException('Registration challenge expired');
		}

		$parsed = $this->jwtVerifier->parse($jwt);
		try {
			$result = $this->jwtVerifier->verifyRegistrationJwt($parsed);
		} catch (JwtInvalidException $e) {
			$this->fail(self::EVENT_REGISTRATION_FAILED, 'Invalid JWT: ' . $e->getMessage(), $ctx);
			throw $e;
		}

		if (!hash_equals($pending->regChallenge, $result->challenge)) {
			$this->fail(self::EVENT_REGISTRATION_FAILED, 'Challenge mismatch.', $ctx);
			throw new JwtInvalidException('Challenge mismatch');
		}

		$now = time();
		$sessionIdentifier = $this->nonce(self::SESSION_ID_BYTES);
		$cookieValue = $this->nonce(self::COOKIE_VALUE_BYTES);
		$binding = new Binding(
			$pending->userId !== '' ? $pending->userId : $ctx->userId,
			$sessionIdentifier,
			$cookieValue,
			$result->publicKeyPem,
			$result->algorithm,
			$this->nonce(),
			$now,
			$now,
			$now,
		);
		$this->store->putBinding($ctx->sessionId, $binding);
		$this->audit->log(self::EVENT_REGISTERED, 'DBSC session registered.', $ctx->userId);

		return new DbscResponse(
			headers: ['Sec-Secure-Session-Id' => $sessionIdentifier],
			cookies: [$this->boundCookie($cookieValue)],
			status: 200,
			body: $this->instructionsJson($sessionIdentifier, $ctx),
			contentType: 'application/json',
		);
	}


	/**
	 * Rotate the cached refresh challenge and return a 403 carrying Secure-Session-Challenge
	 * (with the mandatory `id` sf-parameter) plus Sec-Secure-Session-Id. Use this when the
	 * browser hits /dbsc/refresh with no — or a stale — cached challenge; it caches the new one
	 * and retries. Returns a bare 403 (no binding ⇒ nothing to refresh) if unbound.
	 *
	 * @throws CorruptStateException if the binding record exists but is unreadable
	 */
	public function issueRefreshChallenge(RequestContext $ctx): DbscResponse
	{
		$binding = $this->store->getBinding($ctx->sessionId);
		if (!$binding instanceof Binding) {
			return new DbscResponse(status: 403, contentType: 'application/json');
		}
		$challenge = $this->nonce();
		$rotated = $binding->withChallenge($challenge, time());
		$this->store->putBinding($ctx->sessionId, $rotated);

		return new DbscResponse(
			headers: $this->challengeHeaders($rotated->sessionIdentifier, $challenge),
			status: 403,
			contentType: 'application/json',
		);
	}


	/**
	 * Verify a refresh JWT, reissue the bound cookie, and rotate the cached challenge (the
	 * browser treats a refresh response that re-emits the existing cookie value as "no refresh
	 * happened" and terminates, so BOTH the cookie value and challenge must rotate). Returns 200
	 * with the rotated cookie and the echoed session-instructions JSON.
	 *
	 * On any {@see Exception\RetryableRefreshException} (i.e. {@see MissingChallengeException},
	 * {@see ChallengeExpiredException}, {@see ChallengeMismatchException}) the caller should call
	 * {@see issueRefreshChallenge()} and 403 (benign retry); the audit log records
	 * {@see EVENT_REFRESH_RETRYABLE}, not {@see EVENT_REFRESH_FAILED}, so alerting on the latter
	 * isn't tripped by ordinary concurrency. On any other {@see Exception\DbscException} the
	 * caller MUST terminate the authenticated session server-side — do not rely on the browser or
	 * cookie expiry; that is the failure mode that leaves a stolen-cookie session alive.
	 *
	 * @throws JwtInvalidException
	 * @throws ChallengeExpiredException
	 * @throws ChallengeMismatchException
	 * @throws MissingChallengeException
	 * @throws SessionNotFoundException
	 * @throws CorruptStateException if the binding record exists but is unreadable
	 */
	public function refresh(string $jwt, RequestContext $ctx): DbscResponse
	{
		$binding = $this->store->getBinding($ctx->sessionId);
		if (!$binding instanceof Binding) {
			$this->fail(self::EVENT_REFRESH_FAILED, 'No DBSC session bound.', $ctx);
			throw new SessionNotFoundException('No DBSC session bound to this user');
		}

		$presentedId = $ctx->header('Sec-Secure-Session-Id');
		if (is_string($presentedId) && $presentedId !== '' && !hash_equals($binding->sessionIdentifier, $presentedId)) {
			$this->fail(self::EVENT_REFRESH_FAILED, 'Session identifier mismatch.', $ctx);
			throw new SessionNotFoundException('DBSC session identifier mismatch');
		}

		if ($binding->challenge === '') {
			$this->fail(self::EVENT_REFRESH_RETRYABLE, 'No pending challenge.', $ctx);
			throw new MissingChallengeException('No pending challenge');
		}
		if (time() - $binding->challengeTime > $this->config->challengeTtlSeconds) {
			$this->fail(self::EVENT_REFRESH_RETRYABLE, 'Challenge expired.', $ctx);
			throw new ChallengeExpiredException('Challenge expired');
		}

		$parsed = $this->jwtVerifier->parse($jwt);
		try {
			$jti = $this->jwtVerifier->verifyRefreshJwt($parsed, $binding->publicKeyPem);
		} catch (JwtInvalidException $e) {
			$this->fail(self::EVENT_REFRESH_FAILED, 'Invalid JWT: ' . $e->getMessage(), $ctx);
			throw $e;
		}

		// Accept the current challenge, or the single immediately-previous one until its own TTL
		// (bridges a concurrent issueRefreshChallenge() rotation; see Binding class docblock).
		// Constant-time, current first.
		$challengeMatches = hash_equals($binding->challenge, $jti)
			|| (
				$binding->previousChallenge !== ''
				&& time() - $binding->previousChallengeTime <= $this->config->challengeTtlSeconds
				&& hash_equals($binding->previousChallenge, $jti)
			);
		if (!$challengeMatches) {
			// Signature already verified above (device-bound key) — a mismatch here is benign,
			// never forgery. See ChallengeMismatchException docblock.
			$this->fail(self::EVENT_REFRESH_RETRYABLE, 'Challenge mismatch.', $ctx);
			throw new ChallengeMismatchException('Challenge mismatch');
		}

		$now = time();
		$newCookie = $this->nonce(self::COOKIE_VALUE_BYTES);
		$newChallenge = $this->nonce();
		$rotated = $binding->withRotatedCookieAndChallenge(
			$newCookie,
			$newChallenge,
			$now,
			$now,
			$this->config->cookieMaxAgeSeconds,
		);
		$this->store->putBinding($ctx->sessionId, $rotated);
		$this->audit->log(self::EVENT_REFRESHED, 'DBSC cookie refreshed.', $ctx->userId);

		return new DbscResponse(
			headers: $this->challengeHeaders($rotated->sessionIdentifier, $newChallenge),
			cookies: [$this->boundCookie($newCookie)],
			status: 200,
			body: $this->instructionsJson($rotated->sessionIdentifier, $ctx),
			contentType: 'application/json',
		);
	}


	/**
	 * Terminate DBSC for this session: delete the stored state and emit a deletion for the bound
	 * cookie (it is independent of the application session cookie). Call on logout, and on a
	 * refresh terminal failure *before* you log the user out so the audit row carries the user
	 * id. `$enforcementTerminated` flips the audit event to dbscEnforcementTerminated.
	 */
	public function revoke(RequestContext $ctx, bool $enforcementTerminated = false): DbscResponse
	{
		try {
			$wasBound = $this->store->getBinding($ctx->sessionId) instanceof Binding;
		} catch (CorruptStateException) {
			// A corrupt record still represents a session that must be torn down — delete and
			// audit it; don't let the unreadable value abort revocation (this method is the
			// fail-closed path callers reach precisely when state is unreadable).
			$wasBound = true;
		}
		$this->store->delete($ctx->sessionId);
		if ($wasBound) {
			$this->audit->log(
				$enforcementTerminated ? self::EVENT_ENFORCEMENT_TERMINATED : self::EVENT_REVOKED,
				$enforcementTerminated
					? 'DBSC enforcement terminated session (cookie missing or mismatched).'
					: 'DBSC session revoked.',
				$ctx->userId,
			);
		}
		return new DbscResponse(cookies: [Cookie::deletion($this->config->cookieName)]);
	}


	// --- Enforcement-gate primitives -------------------------------------------------------
	// The library deliberately does not run the gate itself: where you enforce depends on your
	// routing. The recommended policy is in the README — null binding ⇒ degrade to cookie auth;
	// binding present + cookie missing/mismatched on a document request (or a subresource past
	// the registration grace), not on the DBSC endpoints ⇒ revoke + log the user out.

	/**
	 * Null ONLY when the session has no binding (degrade to cookie auth — the recommended
	 * policy). A present-but-unreadable binding throws instead, so the gate fails closed.
	 *
	 * @throws CorruptStateException if the binding record exists but is unreadable
	 */
	public function getBinding(RequestContext $ctx): ?Binding
	{
		return $this->store->getBinding($ctx->sessionId);
	}


	/**
	 * Constant-time comparison of the presented bound cookie against the stored value (which
	 * rotates every refresh) — not mere presence.
	 */
	public function boundCookieMatches(Binding $binding, RequestContext $ctx): bool
	{
		$presented = $ctx->cookie($this->config->cookieName);
		if (!is_string($presented) || $presented === '') {
			return false;
		}
		if ($binding->cookieValue !== '' && hash_equals($binding->cookieValue, $presented)) {
			return true;
		}
		// Accept the single immediately-previous cookie value until the instant it would itself
		// have expired in the browser. The bound cookie rotates on every refresh, but the
		// refresh round-trip is a propagation window: a request that left the browser just
		// before it stored the rotated Set-Cookie still legitimately carries the prior value.
		// Rejecting it as a stolen cookie terminates legitimate sessions whenever a normal
		// request races a refresh (the failure is latency-proportional, so it bites in
		// production and not on loopback). The exposure stays bounded — single-depth history,
		// the value's own natural expiry — and a truly stolen cookie still cannot complete a
		// refresh without the device-bound key, so it still hard-fails at the next refresh.
		if (
			$binding->previousCookieValue !== ''
			&& time() < $binding->previousCookieExpiresAt
			&& hash_equals($binding->previousCookieValue, $presented)
		) {
			return true;
		}
		return false;
	}


	public function isDocumentRequest(RequestContext $ctx): bool
	{
		return $ctx->header('Sec-Fetch-Dest') === 'document';
	}


	public function isWithinRegistrationGrace(Binding $binding): bool
	{
		return time() - $binding->createdAt < $this->config->registrationGraceSeconds;
	}


	/**
	 * Proactively advertise this session's CURRENT cached challenge as a Secure-Session-Challenge
	 * header on an ordinary, already-authenticated response, so the browser holds a challenge when
	 * its first /dbsc/refresh fires and that refresh is single-step instead of the 403-then-proof
	 * two-step.
	 *
	 * No challenge rotation: it re-emits the value the binding already stores (the seed minted by
	 * {@see register()}) — exactly what the next refresh will be asked to prove. It is delivered
	 * exactly ONCE: the browser caches the advertised challenge for the session, so on the single
	 * emission this records `challengeAdvertised` on the binding (one store write, keyed by
	 * `$ctx->sessionId`, mirroring {@see issueRefreshChallenge()}) and every later call no-ops.
	 * The caller passes the binding it already read for the enforcement gate, so the no-op path
	 * costs nothing; only the first delivery touches the store.
	 *
	 * Per spec this MUST NOT be attached to the registration response: the Secure-Session-Challenge
	 * `id` parameter (§9.2.1) must name an EXISTING session, and §8.7 silently drops a challenge
	 * whose session it cannot identify — and the registration response is the very response that
	 * creates the session, so the id resolves to nothing there. Call this only where a Binding
	 * already exists (the enforcement-gate path, which already did {@see getBinding()}); you
	 * cannot misuse it on registration because you have no Binding to pass.
	 *
	 * Returns an empty (no-op) DbscResponse — nothing to apply, nothing written — when there is
	 * nothing safe or useful to advertise: the seed was already delivered, the session has already
	 * refreshed (steady state is self-sustaining), or the cached challenge is empty / past its TTL
	 * (let the reactive 403 path mint a fresh one).
	 */
	public function advertiseRefreshChallenge(Binding $binding, RequestContext $ctx): DbscResponse
	{
		if (
			$binding->challengeAdvertised
			|| $binding->hasRefreshed
			|| $binding->challenge === ''
			|| time() - $binding->challengeTime > $this->config->challengeTtlSeconds
		) {
			return new DbscResponse();
		}
		$this->store->putBinding($ctx->sessionId, $binding->markChallengeAdvertised());
		return new DbscResponse(
			headers: $this->challengeHeaders($binding->sessionIdentifier, $binding->challenge),
		);
	}


	/**
	 * The session-instructions JSON for the current binding, or `{}` if unbound. Echoed on the
	 * refresh 200 so the browser can confirm it is the same session it registered.
	 *
	 * @throws CorruptStateException if the binding record exists but is unreadable
	 */
	public function sessionInstructionsJson(RequestContext $ctx): string
	{
		$binding = $this->store->getBinding($ctx->sessionId);
		if (!$binding instanceof Binding || $binding->sessionIdentifier === '') {
			return '{}';
		}
		return $this->instructionsJson($binding->sessionIdentifier, $ctx);
	}


	private function instructionsJson(string $sessionIdentifier, RequestContext $ctx): string
	{
		$instructions = [
			'session_identifier' => $sessionIdentifier,
			'refresh_url' => $this->config->refreshPath,
			'scope' => [
				'origin' => $ctx->originHostUrl,
				'include_site' => false,
			],
			'credentials' => [
				[
					'type' => 'cookie',
					'name' => $this->config->cookieName,
					'attributes' => sprintf(
						'Path=/; Secure; HttpOnly; SameSite=%s',
						$this->config->cookieSameSite,
					),
				],
			],
		];

		$scopeRules = $this->scopeSpecification($ctx);
		if ($scopeRules !== []) {
			$instructions['scope']['scope_specification'] = array_map(
				static fn (ScopeRule $rule): array => $rule->toArray(),
				$scopeRules,
			);
		}

		$initiators = $this->allowedRefreshInitiators($ctx);
		if ($initiators !== []) {
			$instructions['allowed_refresh_initiators'] = $initiators;
		}

		return json_encode($instructions, JSON_THROW_ON_ERROR);
	}


	/** @return list<ScopeRule> */
	private function scopeSpecification(RequestContext $ctx): array
	{
		return array_values($ctx->scopeSpecification ?? $this->config->scopeSpecification);
	}


	/** @return list<string> */
	private function allowedRefreshInitiators(RequestContext $ctx): array
	{
		$initiators = $ctx->allowedRefreshInitiators ?? $this->config->allowedRefreshInitiators;
		return array_values(array_filter(
			array_map('trim', $initiators),
			static fn (string $host): bool => $host !== '',
		));
	}


	/** @return array<string, string> */
	private function challengeHeaders(string $sessionIdentifier, string $challenge): array
	{
		// Per the W3C draft the challenge structured-field MUST carry an `id` sf-parameter
		// pointing at the session it refers to.
		$idParam = $sessionIdentifier !== '' ? sprintf('; id="%s"', $sessionIdentifier) : '';
		$headers = ['Secure-Session-Challenge' => sprintf('"%s"%s', $challenge, $idParam)];
		if ($sessionIdentifier !== '') {
			$headers['Sec-Secure-Session-Id'] = $sessionIdentifier;
		}
		return $headers;
	}


	private function boundCookie(string $value): Cookie
	{
		return new Cookie(
			$this->config->cookieName,
			$value,
			time() + $this->config->cookieMaxAgeSeconds,
			false,
			'/',
			true,
			true,
			$this->config->cookieSameSite,
		);
	}


	private function nonce(int $bytes = self::NONCE_BYTES): string
	{
		return bin2hex(random_bytes($bytes));
	}


	private function fail(string $event, string $message, RequestContext $ctx): void
	{
		$this->audit->log($event, $message, $ctx->userId);
	}

}
