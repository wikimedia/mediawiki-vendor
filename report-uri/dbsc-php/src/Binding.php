<?php
declare(strict_types = 1);

namespace ReportUri\Dbsc;

use ReportUri\Dbsc\Exception\CorruptStateException;

/**
 * The established device-bound session record. Its existence in the {@see StoreInterface},
 * keyed by the host application's session id, is the authoritative "this session is hard-DBSC"
 * mark. It must NOT be stored in a shared, last-writer-wins session blob: two concurrent
 * requests on the same session (the post-login navigation racing the registration POST) will
 * clobber it and silently disable enforcement. Key it by the stable session id in a dedicated
 * store instead — see README, "Storage".
 *
 * `cookieValue` rotates on every refresh; `sessionIdentifier` is stable and is what the browser
 * echoes back in Sec-Secure-Session-Id.
 *
 * `previousCookieValue` / `previousCookieExpiresAt` retain the single immediately-prior cookie
 * value until the instant that value would itself have expired in the browser. The bound cookie
 * rotates on every refresh, but the refresh round-trip is a propagation window: a request that
 * left the browser just before it stored the rotated Set-Cookie still carries the prior value.
 * Accepting it for the remainder of its own natural lifetime (see {@see DbscServer::boundCookieMatches()})
 * stops the enforcement gate from terminating legitimate sessions over that benign window,
 * without weakening DBSC — only the single most-recent value, only until its own expiry, and an
 * attacker still cannot complete a refresh without the device-bound key.
 *
 * `hasRefreshed` is false from registration and flips true (one-way) on the first successful
 * refresh — the point at which the steady-state loop becomes self-sustaining (every refresh 200
 * carries the next challenge, so the browser always holds one). It exists solely so
 * {@see DbscServer::advertiseRefreshChallenge()} can stop proactively re-advertising the
 * pre-first-refresh seed challenge once it is no longer needed; it is never a security gate.
 *
 * `challengeAdvertised` is false from registration and flips true (one-way) the first time
 * {@see DbscServer::advertiseRefreshChallenge()} actually emits the seed challenge. The browser
 * caches the advertised challenge for the session, so one in-scope delivery is sufficient;
 * without this flag every document navigation in the registration→first-refresh window would
 * re-emit the identical seed (one chrome://dbsc-internals Challenge entry per request, all the
 * same). It gates *delivery frequency* only, never security. It is preserved across
 * {@see withChallenge()} (the reactive 403 rotates the challenge but delivers the new value in
 * its own 403 response, so a re-advertise would be redundant). If the single delivery is lost,
 * the browser simply falls back to today's reactive 403 two-step on its first refresh — no
 * regression, by design.
 *
 * `previousChallenge` / `previousChallengeTime` are the challenge analogue of the cookie-overlap
 * pair, and exist for the same propagation-window reason — but only on the reactive-403 rotation
 * ({@see withChallenge()}). {@see DbscServer::advertiseRefreshChallenge()} can hand the browser
 * the seed challenge while a concurrent {@see DbscServer::issueRefreshChallenge()} rotates it; a
 * request that left the browser carrying the pre-rotation challenge would otherwise
 * challenge-mismatch in {@see DbscServer::refresh()} — a JwtInvalidException, which is the
 * TERMINAL revoke-and-logout path, not the benign 403-retry one. Accepting the single
 * immediately-previous challenge until its own TTL closes that race without weakening DBSC: the
 * challenge is a replay nonce *inside* a JWT the device-bound key must still sign, so an attacker
 * without the key cannot mint a valid refresh for any challenge, current or previous. Single
 * depth, own-expiry-bounded — the same envelope as the cookie overlap. Deliberately NOT retained
 * across a successful refresh ({@see withRotatedCookieAndChallenge()} clears it): the refresh 200
 * delivers the new challenge synchronously with the rotated cookie, so there is no success-path
 * propagation window to bridge, and not retaining it keeps the spent challenge from being
 * replayable. This asymmetry vs the cookie overlap (which IS retained across refresh) is
 * intentional — do not consistency-refactor the two into one.
 */
final class Binding
{

	public function __construct(
		public readonly string $userId,
		public readonly string $sessionIdentifier,
		public readonly string $cookieValue,
		public readonly string $publicKeyPem,
		public readonly string $algorithm,
		public readonly string $challenge,
		public readonly int $challengeTime,
		public readonly int $createdAt,
		public readonly int $cookieIssuedAt = 0,
		public readonly string $previousCookieValue = '',
		public readonly int $previousCookieExpiresAt = 0,
		public readonly bool $hasRefreshed = false,
		public readonly string $previousChallenge = '',
		public readonly int $previousChallengeTime = 0,
		public readonly bool $challengeAdvertised = false,
	) {
	}


	public function toJson(): string
	{
		return json_encode([
			'userId' => $this->userId,
			'sessionIdentifier' => $this->sessionIdentifier,
			'cookieValue' => $this->cookieValue,
			'publicKeyPem' => $this->publicKeyPem,
			'algorithm' => $this->algorithm,
			'challenge' => $this->challenge,
			'challengeTime' => $this->challengeTime,
			'createdAt' => $this->createdAt,
			'cookieIssuedAt' => $this->cookieIssuedAt,
			'previousCookieValue' => $this->previousCookieValue,
			'previousCookieExpiresAt' => $this->previousCookieExpiresAt,
			'hasRefreshed' => $this->hasRefreshed,
			'previousChallenge' => $this->previousChallenge,
			'previousChallengeTime' => $this->previousChallengeTime,
			'challengeAdvertised' => $this->challengeAdvertised,
		], JSON_THROW_ON_ERROR);
	}


	/**
	 * Parse a stored binding. Throws on a present-but-unreadable value rather than returning
	 * null: callers MUST treat that as fail-closed and NOT collapse it to "no binding", which
	 * would degrade a bound session to plain cookie auth — the fail-open DBSC exists to prevent.
	 * Absence is represented by the store returning null without ever calling this method.
	 *
	 * The cookie-rotation-overlap fields (cookieIssuedAt / previousCookieValue /
	 * previousCookieExpiresAt), hasRefreshed, challengeAdvertised, and the challenge-overlap pair
	 * (previousChallenge / previousChallengeTime) are OPTIONAL with safe defaults: records written
	 * by an older library version predate them, and a record without them is fully valid — it
	 * simply has no previous-value overlap (strict current-value match only), hasRefreshed defaults
	 * false so the proactive seed challenge is re-advertised until its next refresh re-issues it
	 * under the current code, challengeAdvertised defaults false so the seed is delivered once more
	 * (a harmless duplicate at worst), and the challenge overlap is empty until the next
	 * reactive-403 rotation populates it. That is a graceful, fail-closed default, never a lockout
	 * and never a fail-open. Every field that was required stays strictly type-checked.
	 *
	 * @throws CorruptStateException
	 */
	public static function fromJson(string $json): self
	{
		$data = json_decode($json, true);
		if (!is_array($data)) {
			throw new CorruptStateException('Binding record is not a JSON object');
		}
		$userId = $data['userId'] ?? null;
		$sessionIdentifier = $data['sessionIdentifier'] ?? null;
		$cookieValue = $data['cookieValue'] ?? null;
		$publicKeyPem = $data['publicKeyPem'] ?? null;
		$algorithm = $data['algorithm'] ?? null;
		$challenge = $data['challenge'] ?? null;
		$challengeTime = $data['challengeTime'] ?? null;
		$createdAt = $data['createdAt'] ?? null;
		if (
			!is_string($userId) ||
			!is_string($sessionIdentifier) ||
			!is_string($cookieValue) ||
			!is_string($publicKeyPem) ||
			!is_string($algorithm) ||
			!is_string($challenge) ||
			!is_int($challengeTime) ||
			!is_int($createdAt)
		) {
			throw new CorruptStateException('Binding record has missing or wrong-typed fields');
		}
		$cookieIssuedAt = $data['cookieIssuedAt'] ?? null;
		$previousCookieValue = $data['previousCookieValue'] ?? null;
		$previousCookieExpiresAt = $data['previousCookieExpiresAt'] ?? null;
		$hasRefreshed = $data['hasRefreshed'] ?? null;
		$previousChallenge = $data['previousChallenge'] ?? null;
		$previousChallengeTime = $data['previousChallengeTime'] ?? null;
		$challengeAdvertised = $data['challengeAdvertised'] ?? null;
		return new self(
			$userId,
			$sessionIdentifier,
			$cookieValue,
			$publicKeyPem,
			$algorithm,
			$challenge,
			$challengeTime,
			$createdAt,
			is_int($cookieIssuedAt) ? $cookieIssuedAt : $createdAt,
			is_string($previousCookieValue) ? $previousCookieValue : '',
			is_int($previousCookieExpiresAt) ? $previousCookieExpiresAt : 0,
			is_bool($hasRefreshed) ? $hasRefreshed : false,
			is_string($previousChallenge) ? $previousChallenge : '',
			is_int($previousChallengeTime) ? $previousChallengeTime : 0,
			is_bool($challengeAdvertised) ? $challengeAdvertised : false,
		);
	}


	/**
	 * Rotate ONLY the cached challenge (the reactive-403 path). The value being demoted is
	 * retained as `previousChallenge` / `previousChallengeTime` and accepted by
	 * {@see DbscServer::refresh()} until its own TTL, bridging the propagation window where
	 * {@see DbscServer::advertiseRefreshChallenge()} handed the browser the pre-rotation value.
	 * Single-depth: a second reactive rotation discards the challenge from two rotations ago. An
	 * empty current challenge is not retained (nothing to bridge to).
	 *
	 * `challengeAdvertised` is preserved, not reset: this 403 already delivered the rotated
	 * challenge to the browser in its own response, so re-advertising it on a later document
	 * response would be the redundant emission the flag exists to prevent.
	 */
	public function withChallenge(string $challenge, int $challengeTime): self
	{
		return new self(
			$this->userId,
			$this->sessionIdentifier,
			$this->cookieValue,
			$this->publicKeyPem,
			$this->algorithm,
			$challenge,
			$challengeTime,
			$this->createdAt,
			$this->cookieIssuedAt,
			$this->previousCookieValue,
			$this->previousCookieExpiresAt,
			$this->hasRefreshed,
			$this->challenge,
			$this->challengeTime,
			$this->challengeAdvertised,
		);
	}


	/**
	 * Flip `challengeAdvertised` true (one-way, idempotent): called by
	 * {@see DbscServer::advertiseRefreshChallenge()} the first time it actually emits the seed,
	 * so subsequent document responses in the pre-first-refresh window stay silent.
	 */
	public function markChallengeAdvertised(): self
	{
		return new self(
			$this->userId,
			$this->sessionIdentifier,
			$this->cookieValue,
			$this->publicKeyPem,
			$this->algorithm,
			$this->challenge,
			$this->challengeTime,
			$this->createdAt,
			$this->cookieIssuedAt,
			$this->previousCookieValue,
			$this->previousCookieExpiresAt,
			$this->hasRefreshed,
			$this->previousChallenge,
			$this->previousChallengeTime,
			true,
		);
	}


	/**
	 * Rotate the bound cookie (and challenge). The value being demoted is retained as
	 * `previousCookieValue`, accepted only until `previousCookieExpiresAt` — the instant the
	 * demoted cookie would itself have expired in the browser, i.e. its own issuance time
	 * (`$this->cookieIssuedAt`) plus the configured max-age. Single-depth: a second rotation
	 * discards the value from two rotations ago.
	 *
	 * Also flips `hasRefreshed` true (one-way, idempotent on later rotations): a successful
	 * refresh is exactly the point past which the proactive seed-challenge advertisement is no
	 * longer needed.
	 *
	 * Unlike {@see withChallenge()}, this deliberately does NOT retain the just-proved challenge
	 * as `previousChallenge` (it resets the pair to empty): the refresh 200 hands the browser the
	 * new challenge synchronously with the rotated cookie, so there is no propagation window to
	 * bridge here, and dropping the spent challenge keeps it from being replayable. The cookie
	 * IS retained across this same transition because the cookie genuinely has such a window —
	 * the asymmetry is intentional. `challengeAdvertised` is carried through unchanged; it is moot
	 * post-refresh anyway since `hasRefreshed` now gates the advertise path.
	 */
	public function withRotatedCookieAndChallenge(
		string $cookieValue,
		string $challenge,
		int $challengeTime,
		int $now,
		int $cookieMaxAgeSeconds,
	): self {
		return new self(
			$this->userId,
			$this->sessionIdentifier,
			$cookieValue,
			$this->publicKeyPem,
			$this->algorithm,
			$challenge,
			$challengeTime,
			$this->createdAt,
			$now,
			$this->cookieValue,
			$this->cookieIssuedAt + $cookieMaxAgeSeconds,
			true,
			'',
			0,
			$this->challengeAdvertised,
		);
	}

}
