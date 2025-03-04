<?php
namespace SmashPig\PaymentData;

/**
 * Allowed values for final status of a payment attempt
 */
class FinalStatus {
	const COMPLETE = 'complete';
	const FAILED = 'failed';
	const PENDING = 'pending';
	const PENDING_POKE = 'pending-poke';
	const REVISED = 'revised';
	const REVERSED = 'reversed';
	const REFUNDED = 'refunded';
	const ON_HOLD = 'on-hold';
	const CANCELLED = 'cancelled';
	const TIMEOUT = 'timeout';
	const UNKNOWN = 'unknown';
}
