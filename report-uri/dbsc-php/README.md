[![Licensed under the MIT License](https://img.shields.io/badge/License-MIT-blue.svg)](https://github.com/report-uri/dbsc-php/blob/main/LICENSE)
[![PHP 8.1+](https://img.shields.io/badge/PHP-8.1+-green.svg)](https://php.net)

# dbsc-php
*A small, framework-agnostic PHP server library for Device Bound Session Credentials (DBSC).*

[DBSC](https://github.com/w3c/webappsec-dbsc) cryptographically binds an authenticated session to a hardware-backed device key (TPM / secure enclave). A stolen session cookie can no longer be replayed from another device: the short-lived bound cookie expires every few minutes and is only refreshable by signing a server challenge with a private key that never leaves the device.

It is pure HTTP headers — **no JavaScript, no frontend assets, no database tables required**. Non-DBSC browsers simply ignore the registration header and continue on normal cookie auth, so enabling it cannot lock anyone out.

This library is extracted from [Report URI](https://report-uri.com)'s production DBSC integration ([report-uri/passkeys-php](https://github.com/report-uri/passkeys-php) is its passkeys sibling). It carries the wire-protocol corrections that only surface when integrating against a real browser — see [Wire-protocol notes](#wire-protocol-notes).

## Design

- **Zero dependencies** beyond `ext-openssl` / `ext-json`. ~700 lines, auditable in one sitting.
- **Framework-agnostic.** The library never reads superglobals, sends a header, or sets a cookie. Every operation takes a `RequestContext` you build from your framework's request and returns a `DbscResponse` you apply to your framework's response.
- **Storage is yours.** You implement `StoreInterface` (Redis, a table, …). An `InMemoryStore` is bundled for tests and the demo.
- **The crypto is deliberately minimal** — ES256 only, signature + single-use challenge nonce. See the class docblock on `JwtVerifier` for why `iat`/`exp`/`iss`/`aud` are intentionally *not* checked.

## Installation

```bash
composer require report-uri/dbsc-php
```

Autoloads under PSR-4 as `ReportUri\Dbsc\`. The entry point is `ReportUri\Dbsc\DbscServer`.

```php
use ReportUri\Dbsc\{Config, DbscServer};

$dbsc = new DbscServer(new Config(cookieName: '__Host-myapp_dbsc'), $myStore);
```

## Flow

```
        BROWSER (DBSC-capable)              YOUR APP
   ----------------------------------------------------------------
   GET  /login  (full auth done) ----->  buildRegistrationHeaderResponse()
                                <-----   Secure-Session-Registration: (ES256); ...
   POST /dbsc/register (signed JWT) -->  register()
                                <-----   200 + Set-Cookie __Host-…_dbsc + Sec-Secure-Session-Id
                                          + session-instructions JSON
   ... every ~few minutes ...
   POST /dbsc/refresh (no body)  ----->  issueRefreshChallenge()
                                <-----   403 + Secure-Session-Challenge="…"; id="…"
   POST /dbsc/refresh (signed JWT) -->  refresh()
                                <-----   200 + rotated Set-Cookie + new challenge
   GET  /account (every request) ----->  enforcement gate (see below)
   GET  /logout                  ----->  revoke()
```

A complete reference front controller is in [`_test/server.php`](_test/server.php). DBSC is browser-native (no JS API to script), so exercise it with a DBSC-capable browser over HTTPS.

## Enforcement gate

The library exposes the primitives but does **not** run the gate itself — *where* you enforce depends on your routing. The recommended policy (also in `_test/server.php`):

```php
$binding = $dbsc->getBinding($ctx);

if ($binding === null) {
    // Never registered: unsupported browser, or not yet. Degrade to normal cookie auth.
    // (Do NOT block here — this is what makes locking out a Firefox user impossible.)
}

$mustCheck = $dbsc->isDocumentRequest($ctx) || !$dbsc->isWithinRegistrationGrace($binding);
if ($mustCheck && !$dbsc->boundCookieMatches($binding, $ctx)) {
    // Bound session, bad/absent device cookie -> revoke + log the user out, redirect to login.
    $resp = $dbsc->revoke($ctx, enforcementTerminated: true);
}
```

Enforce on document loads **and** on subresources past the registration grace — *not* document-only, which would let a stolen cookie exfiltrate via XHR within the cookie lifetime. Skip the gate on the `/dbsc/*` endpoints themselves.

### `refresh()` failure contract

```php
try {
    emit($dbsc->refresh($jwt, $ctx));
} catch (RetryableRefreshException) {
    emit($dbsc->issueRefreshChallenge($ctx)); // benign: 403, browser retries
} catch (DbscException $e) {
    emit($dbsc->revoke($ctx, enforcementTerminated: true)); // terminal: stolen-cookie signal
}
```

`MissingChallengeException`, `ChallengeExpiredException`, and `ChallengeMismatchException` implement `RetryableRefreshException` — catch it once instead of enumerating three classes. `ChallengeMismatchException` belongs there, not in the terminal set, because `refresh()` only reaches the challenge comparison after the JWT signature has already verified against the device key — a mismatch at that point can only be a benign race (idle session, concurrent refresh, lost 403), never forgery. This is visible via the audit logs too: a `RetryableRefreshException` logs `DbscServer::EVENT_REFRESH_RETRYABLE` (`dbscRefreshRetryable`) .

**Backward compatibility:** all three still `extends DbscException`, so an existing `catch (DbscException) { revoke(...) }` keeps compiling — but it also keeps today's bug (a benign mismatch still force-logs the user out) until you add a `catch (RetryableRefreshException)` ahead of it.

## Storage

Key DBSC state by your **stable session id**, in a **dedicated key space** — never in a read-modify-written shared session blob.

> This is the one non-obvious correctness requirement. Report URI shipped DBSC with state in the PHP session blob; the post-login navigation races the `/dbsc/register` POST, both rewrite the whole blob last-writer-wins, the binding is clobbered, and enforcement silently no-ops — leaving exactly the stolen-cookie hole DBSC exists to close. `StoreInterface` documents the requirements; back it with Redis or a table keyed by session id.

Pending registrations expire on the challenge TTL; bindings expire with the session lifetime.

## Wire-protocol notes

Baked into this library from integration testing against real Chrome — change with care:

- **Registration is single-phase; refresh is two-phase** (403 + challenge, then 200). This is the opposite of how the spec reads at first glance.
- **The *first* refresh can optionally be made single-phase.** Steady-state refreshes already are (every 200 hands back the next challenge). Call `advertiseRefreshChallenge($binding, $ctx)` on an ordinary authenticated *document* response in the registration→first-refresh window and it attaches the seed `Secure-Session-Challenge` once (recording a one-way mark on the binding so later responses stay silent), so the browser holds a challenge when its first `/dbsc/refresh` fires and skips the 403. Spec-mandated: never attach this to the registration response (§9.2.1/§8.7 — the `id` must name an already-existing session); the method takes a `Binding`, which only exists post-registration, so misuse is structurally impossible. If the single delivery is missed the browser simply falls back to the two-phase path — no regression. A reactive 403 racing the advertised value is covered by single-depth challenge overlap (the immediately-previous challenge is accepted in `refresh()` until its own TTL), mirroring the bound-cookie overlap.
- **No `Secure-Session-Challenge` on the registration response** — Chrome reports a Challenge Error. The first refresh-flow 403 issues the challenge; the binding seeds an internal one only to stay valid until then.
- **Both the cookie value and the challenge must rotate on every refresh.** Re-emitting the existing cookie value makes Chrome treat it as "no refresh happened" and terminate.
- **`Secure-Session-Challenge` must carry the `id` sf-parameter** naming the session.
- **`challengeTtl` must exceed `cookieMaxAge`** (the `Config` constructor enforces this) so a challenge the browser cached just before cookie expiry is still valid when it is used.
- **The bound cookie uses `__Host-`**, so `include_site` is `false` (no subdomain span).
- **Scope the session to what it protects, not to the whole origin.** This is the default that
  costs you, and it is not obvious. Scope decides which requests the browser DEFERS while it
  refreshes an expired bound cookie, and every refresh spends a signature from a rate-limited device
  key. With the default whole-origin scope, your static assets are in it — so a cold page load with
  an expired cookie fires every stylesheet, script and icon at once and the browser attempts a
  **separate refresh for each**; it does not coalesce them. Measured against Chrome 151: seven
  assets, seven signing attempts in the same second, `quota_exceeded`, and a wedged session. Once
  the quota is gone refreshes stop, the browser will not register a replacement for a scope it
  already covers, and the site becomes indistinguishable from one with no DBSC support at all —
  only clearing site data recovers it. Exclude anything that needs no session:

  ```php
  new Config(scopeSpecification: [
      ScopeRule::exclude(path: '/assets/'),
      ScopeRule::exclude(path: '/healthz'),
  ]);
  ```

  Omitted from the wire entirely when empty (the spec default). Overridable per request via
  `RequestContext(..., scopeSpecification: [...])`, where `null` inherits the `Config` value and
  `[]` forces the key off. `path` is a **prefix**, not an exact match.

- **`allowed_refresh_initiators`** ([spec](https://w3c.github.io/webappsec-dbsc/#allowed-refresh-initiators)) lists out-of-scope hosts allowed to trigger a refresh on a cross-site-initiated navigation — by default Chrome refuses (a timing side-channel mitigation). Omitted when empty (the spec default). Set a static default via `Config(allowedRefreshInitiators: [...])`, or override per request via `RequestContext(..., allowedRefreshInitiators: [...])` (`null` falls back to `Config`, `[]` forces the key off). Entries pass through verbatim, wildcards included. **Security note:** each listed host regains the authentication-state timing oracle this mitigation removes — list only relying parties you trust.

## Tests

```bash
php _test/run-tests.php
```

A self-contained harness (no PHPUnit): it generates a real EC P-256 device key, builds the JWTs exactly as Chrome does, and drives the full register/refresh/enforce/revoke flow plus the attack cases (wrong device key, wrong/expired/mismatched challenge, stale cookie, `alg=none`).

## License

MIT — see [LICENSE](LICENSE). © 2026 Report-URI Ltd.
