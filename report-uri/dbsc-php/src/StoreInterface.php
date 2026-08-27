<?php
declare(strict_types = 1);

namespace ReportUri\Dbsc;

/**
 * Persistence for DBSC state, keyed by the host application's session id.
 *
 * Implementation requirements:
 *  - The pending-registration and binding records MUST be stored under separate keys. "A binding
 *    exists" is the authoritative hard-DBSC mark; conflating it with the pending challenge would
 *    let a browser that merely *received* the registration offer be treated as bound.
 *  - State MUST NOT live in a read-modify-written shared session blob (last-writer-wins). Use a
 *    dedicated key space — Redis, a table, etc. See README, "Storage".
 *  - Pending registrations should expire on a short TTL (the challenge TTL). Bindings should
 *    expire with the authenticated session lifetime.
 *  - getBinding()/getPendingRegistration() MUST return null ONLY when no record exists. A
 *    record that is present but unparseable MUST throw {@see Exception\CorruptStateException}
 *    (the bundled value objects' fromJson() already does this — call it only for a record that
 *    exists). Returning null for corrupt data would degrade a bound session to plain cookie
 *    auth, the fail-open DBSC exists to prevent.
 */
interface StoreInterface
{

	public function putPendingRegistration(string $sessionId, PendingRegistration $pending): void;


	public function getPendingRegistration(string $sessionId): ?PendingRegistration;


	public function deletePendingRegistration(string $sessionId): void;


	public function putBinding(string $sessionId, Binding $binding): void;


	public function getBinding(string $sessionId): ?Binding;


	public function delete(string $sessionId): void;

}
