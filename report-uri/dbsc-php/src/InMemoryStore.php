<?php
declare(strict_types = 1);

namespace ReportUri\Dbsc;

/**
 * Reference {@see StoreInterface} that keeps state in process memory with TTL semantics.
 *
 * Intended for tests, the bundled demo, and single-process experimentation only. It does not
 * survive a request in a typical PHP-FPM deployment — ship a Redis- or database-backed store in
 * production (see README, "Storage", and _test/server.php for a session-backed example).
 */
final class InMemoryStore implements StoreInterface
{

	/** @var array<string, array{value: string, expiresAt: int}> */
	private array $reg = [];

	/** @var array<string, array{value: string, expiresAt: int}> */
	private array $bind = [];


	public function __construct(
		private readonly int $challengeTtlSeconds = 900,
		private readonly int $sessionLifetimeSeconds = 64800,
	) {
	}


	public function putPendingRegistration(string $sessionId, PendingRegistration $pending): void
	{
		$this->reg[$sessionId] = ['value' => $pending->toJson(), 'expiresAt' => time() + $this->challengeTtlSeconds];
	}


	public function getPendingRegistration(string $sessionId): ?PendingRegistration
	{
		$raw = $this->live($this->reg, $sessionId);
		return $raw === null ? null : PendingRegistration::fromJson($raw);
	}


	public function deletePendingRegistration(string $sessionId): void
	{
		unset($this->reg[$sessionId]);
	}


	public function putBinding(string $sessionId, Binding $binding): void
	{
		$this->bind[$sessionId] = ['value' => $binding->toJson(), 'expiresAt' => time() + $this->sessionLifetimeSeconds];
	}


	public function getBinding(string $sessionId): ?Binding
	{
		$raw = $this->live($this->bind, $sessionId);
		return $raw === null ? null : Binding::fromJson($raw);
	}


	public function delete(string $sessionId): void
	{
		unset($this->bind[$sessionId], $this->reg[$sessionId]);
	}


	/**
	 * @param array<string, array{value: string, expiresAt: int}> $bucket
	 */
	private function live(array $bucket, string $sessionId): ?string
	{
		$entry = $bucket[$sessionId] ?? null;
		if ($entry === null || $entry['expiresAt'] < time()) {
			return null;
		}
		return $entry['value'];
	}

}
