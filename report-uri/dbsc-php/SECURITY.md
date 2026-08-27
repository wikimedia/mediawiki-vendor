# Security Policy

`report-uri/dbsc-php` is a security library: it verifies the device-bound
signatures that protect authenticated sessions against cookie theft. A flaw
here can silently downgrade every consumer's session protection, so reports are
taken seriously and triaged quickly.

## Reporting a vulnerability

**Do not open a public issue, pull request, or discussion for a suspected
vulnerability.** Public disclosure before a fix is available puts every
downstream application at risk.

Instead, use **GitHub Private Vulnerability Reporting**:

> Repository → **Security** tab → **Report a vulnerability**

This opens a private advisory visible only to the maintainers. Please include:

- the affected version(s) / commit,
- a description of the issue and its security impact,
- reproduction steps or a proof of concept,
- any suggested remediation.

We aim to acknowledge a report within **3 working days** and to provide a
remediation timeline after initial triage. Please allow a reasonable
coordinated-disclosure window before any public write-up; we are happy to
credit reporters in the advisory and release notes.

## Scope

In scope:

- Signature / JWT verification (`src/JwtVerifier.php`).
- The registration / refresh / enforcement state machine (`src/DbscServer.php`).
- Anything that could let a stolen session cookie be replayed, a binding be
  forged or bypassed, the challenge nonce be replayed, or the enforcement gate
  be silently disabled.

Out of scope:

- Misuse in a consuming application (e.g. storing DBSC state in a
  read-modify-written shared session blob — see `README.md` → *Storage*).
- The bundled `_test/` reference code, which is illustrative and not intended
  for production use.

## Supported versions

Until a `1.0.0` release, only the latest tagged release on the default branch
receives security fixes.
