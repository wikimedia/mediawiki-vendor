<?php
declare(strict_types = 1);

namespace ReportUri\Dbsc;

use ReportUri\Dbsc\Exception\CorruptStateException;

/**
 * The short-lived registration challenge, written when the Secure-Session-Registration header is
 * emitted at login — before the browser has proven DBSC support. Kept in its own store key
 * (separate from {@see Binding}) so that "a binding exists" is true only after a successful
 * registration, never merely because DBSC was offered. Never consulted by the enforcement gate.
 */
final class PendingRegistration
{

	public function __construct(
		public readonly string $userId,
		public readonly string $regChallenge,
		public readonly int $regChallengeTime,
	) {
	}


	public function toJson(): string
	{
		return json_encode([
			'userId' => $this->userId,
			'regChallenge' => $this->regChallenge,
			'regChallengeTime' => $this->regChallengeTime,
		], JSON_THROW_ON_ERROR);
	}


	/**
	 * Parse a stored pending registration. Throws on a present-but-unreadable value rather than
	 * returning null, so a corrupt record fails closed rather than registering on garbage.
	 * Absence is represented by the store returning null without ever calling this method.
	 *
	 * @throws CorruptStateException
	 */
	public static function fromJson(string $json): self
	{
		$data = json_decode($json, true);
		if (!is_array($data)) {
			throw new CorruptStateException('Pending registration record is not a JSON object');
		}
		$userId = $data['userId'] ?? null;
		$regChallenge = $data['regChallenge'] ?? null;
		$regChallengeTime = $data['regChallengeTime'] ?? null;
		if (!is_string($userId) || !is_string($regChallenge) || !is_int($regChallengeTime)) {
			throw new CorruptStateException('Pending registration record has missing or wrong-typed fields');
		}
		return new self($userId, $regChallenge, $regChallengeTime);
	}

}
