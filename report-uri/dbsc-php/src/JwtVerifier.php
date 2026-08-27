<?php
declare(strict_types = 1);

namespace ReportUri\Dbsc;

use ReportUri\Dbsc\Exception\JwtInvalidException;

/**
 * Verifies the registration and refresh JWTs presented by the browser's DBSC implementation.
 *
 * Deliberately minimal validation — we check only `alg`, the signature, and `jti`:
 *  - `alg` is pinned to ES256 to block algorithm confusion (`none`, RS-with-EC-key).
 *  - The signature binds to the embedded JWK on registration and to the stored PEM on refresh,
 *    so a JWT minted against another integration cannot produce a matching signature here.
 *  - `jti` is matched (by the caller) against the currently-stored single-use server challenge,
 *    which has its own TTL. That nonce is the replay defence.
 *
 * We do NOT check `iat`/`exp`/`typ`/`iss`/`aud`: the W3C DBSC draft lists them as optional and
 * browser emission is not stable across versions; the challenge TTL is already stricter than any
 * `exp` a browser would emit. Tighten here if a future spec revision mandates more claims —
 * don't speculatively add checks that only risk false rejects.
 */
final class JwtVerifier
{

	private const SUPPORTED_ALG = 'ES256';

	// DER prefix for an EC P-256 SubjectPublicKeyInfo: SEQUENCE { ecPublicKey, prime256v1 }.
	private const EC_P256_OID_DER = "\x30\x13\x06\x07\x2A\x86\x48\xCE\x3D\x02\x01\x06\x08\x2A\x86\x48\xCE\x3D\x03\x01\x07";


	/**
	 * @return array{header: array<string, mixed>, payload: array<string, mixed>, signingInput: string, signature: string}
	 * @throws JwtInvalidException
	 */
	public function parse(string $jwt): array
	{
		$parts = explode('.', $jwt);
		if (count($parts) !== 3) {
			throw new JwtInvalidException('JWT must have three parts');
		}
		[$headerB64, $payloadB64, $signatureB64] = $parts;

		$header = json_decode($this->base64UrlDecode($headerB64), true);
		$payload = json_decode($this->base64UrlDecode($payloadB64), true);
		if (!is_array($header) || !is_array($payload)) {
			throw new JwtInvalidException('JWT header or payload is not a JSON object');
		}

		$signature = $this->base64UrlDecode($signatureB64);
		if ($signature === '') {
			throw new JwtInvalidException('JWT signature is empty');
		}

		return [
			'header' => $header,
			'payload' => $payload,
			'signingInput' => $headerB64 . '.' . $payloadB64,
			'signature' => $signature,
		];
	}


	/**
	 * Verify a registration JWT — the device public key is embedded in the JWT header as a JWK.
	 *
	 * @param array{header: array<string, mixed>, payload: array<string, mixed>, signingInput: string, signature: string} $parsed
	 * @throws JwtInvalidException
	 */
	public function verifyRegistrationJwt(array $parsed): RegistrationResult
	{
		$header = $parsed['header'];
		if (($header['alg'] ?? null) !== self::SUPPORTED_ALG) {
			throw new JwtInvalidException('Unsupported algorithm');
		}
		if (!isset($header['jwk']) || !is_array($header['jwk'])) {
			throw new JwtInvalidException('JWT header is missing jwk');
		}
		$pem = $this->jwkToPem($header['jwk']);

		if (!$this->verifySignature($parsed['signingInput'], $parsed['signature'], $pem)) {
			throw new JwtInvalidException('JWT signature verification failed');
		}

		return new RegistrationResult($pem, self::SUPPORTED_ALG, $this->requireJti($parsed['payload']));
	}


	/**
	 * Verify a refresh JWT against the device PEM stored at registration.
	 *
	 * @param array{header: array<string, mixed>, payload: array<string, mixed>, signingInput: string, signature: string} $parsed
	 * @return string the `jti` claim (matched against the stored challenge by the caller)
	 * @throws JwtInvalidException
	 */
	public function verifyRefreshJwt(array $parsed, string $pem): string
	{
		$header = $parsed['header'];
		if (($header['alg'] ?? null) !== self::SUPPORTED_ALG) {
			throw new JwtInvalidException('Unsupported algorithm');
		}
		if (!$this->verifySignature($parsed['signingInput'], $parsed['signature'], $pem)) {
			throw new JwtInvalidException('JWT signature verification failed');
		}
		return $this->requireJti($parsed['payload']);
	}


	/**
	 * @param array<string, mixed> $payload
	 * @throws JwtInvalidException
	 */
	private function requireJti(array $payload): string
	{
		$jti = $payload['jti'] ?? null;
		if (!is_string($jti) || $jti === '') {
			throw new JwtInvalidException('JWT payload is missing jti');
		}
		return $jti;
	}


	private function verifySignature(string $signingInput, string $rawSignature, string $pem): bool
	{
		// DBSC ES256 signatures are the raw 64-byte (r||s) concatenation; OpenSSL expects DER.
		if (strlen($rawSignature) !== 64) {
			return false;
		}
		$key = openssl_pkey_get_public($pem);
		if ($key === false) {
			return false;
		}
		return openssl_verify($signingInput, $this->rawEcdsaToDer($rawSignature), $key, OPENSSL_ALGO_SHA256) === 1;
	}


	/**
	 * @param array<string, mixed> $jwk
	 * @throws JwtInvalidException
	 */
	private function jwkToPem(array $jwk): string
	{
		if (($jwk['kty'] ?? null) !== 'EC' || ($jwk['crv'] ?? null) !== 'P-256') {
			throw new JwtInvalidException('Unsupported JWK key type or curve');
		}
		$x = is_string($jwk['x'] ?? null) ? $this->base64UrlDecode($jwk['x']) : '';
		$y = is_string($jwk['y'] ?? null) ? $this->base64UrlDecode($jwk['y']) : '';
		if (strlen($x) !== 32 || strlen($y) !== 32) {
			throw new JwtInvalidException('JWK x/y coordinates are the wrong length');
		}

		// SubjectPublicKeyInfo for P-256: SEQUENCE { AlgorithmIdentifier, BIT STRING { 04 || X || Y } }
		$bitString = "\x03\x42\x00\x04" . $x . $y;
		$spki = "\x30\x59" . self::EC_P256_OID_DER . $bitString;

		return "-----BEGIN PUBLIC KEY-----\n" . chunk_split(base64_encode($spki), 64, "\n") . "-----END PUBLIC KEY-----\n";
	}


	private function rawEcdsaToDer(string $raw): string
	{
		$body = $this->derInteger(substr($raw, 0, 32)) . $this->derInteger(substr($raw, 32, 32));
		return "\x30" . $this->derLength(strlen($body)) . $body;
	}


	private function derInteger(string $bytes): string
	{
		$bytes = ltrim($bytes, "\x00");
		if ($bytes === '' || (ord($bytes[0]) & 0x80) !== 0) {
			$bytes = "\x00" . $bytes;
		}
		return "\x02" . $this->derLength(strlen($bytes)) . $bytes;
	}


	private function derLength(int $length): string
	{
		if ($length < 0x80) {
			return chr($length);
		}
		$bytes = '';
		while ($length > 0) {
			$bytes = chr($length & 0xFF) . $bytes;
			$length >>= 8;
		}
		return chr(0x80 | strlen($bytes)) . $bytes;
	}


	private function base64UrlDecode(string $input): string
	{
		$decoded = base64_decode(strtr($input, '-_', '+/'), true);
		return $decoded === false ? '' : $decoded;
	}

}
