<?php
declare(strict_types = 1);

namespace ReportUri\Dbsc;

/**
 * The outcome of verifying a registration JWT: the device public key (PEM, extracted from the
 * embedded JWK), the algorithm, and the `jti` the device signed (matched against the issued
 * registration challenge by the caller).
 */
final class RegistrationResult
{

	public function __construct(
		public readonly string $publicKeyPem,
		public readonly string $algorithm,
		public readonly string $challenge,
	) {
	}

}
