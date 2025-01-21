<?php

namespace SmashPig\PaymentProviders\Ingenico;

use SmashPig\PaymentData\FinalStatus;
use SmashPig\PaymentData\StatusNormalizer;
use UnexpectedValueException;

/**
 * Documented at:
 * https://epayments-api.developer-ingenico.com/s2sapi/v1/en_US/java/statuses.html
 */
class PaymentStatus implements StatusNormalizer {

	// Initial state, before donor has entered anything
	const CREATED = 'CREATED';
	// We cancelled the transaction
	const CANCELLED = 'CANCELLED';
	// Payment rejected, probably at authorization stage
	const REJECTED = 'REJECTED';
	// Got to the capture stage, but couldn't complete
	const REJECTED_CAPTURE = 'REJECTED_CAPTURE';
	// Donor sent to 3rd party site
	const REDIRECTED = 'REDIRECTED';
	// Donor has been instructed to deliver money but has not yet
	const PENDING_PAYMENT = 'PENDING_PAYMENT';
	// Verification with $0 auth - we don't use this
	const ACCOUNT_VERIFIED = 'ACCOUNT_VERIFIED';
	// We need to approve the capture (single-capture version)
	const PENDING_APPROVAL = 'PENDING_APPROVAL';
	// We need to approve a capture, and can do so with multiple partial captures
	const PENDING_CAPTURE = 'PENDING_CAPTURE';
	// Marked for review, we need to approve
	const PENDING_FRAUD_APPROVAL = 'PENDING_FRAUD_APPROVAL';
	// Waiting for asynchronous authorization
	const AUTHORIZATION_REQUESTED = 'AUTHORIZATION_REQUESTED';
	// Waiting to be captured
	const CAPTURE_REQUESTED = 'CAPTURE_REQUESTED';
	// All set, we charged the card
	const CAPTURED = 'CAPTURED';
	// Donor has delivered the promised funds
	const PAID = 'PAID';
	// Donor regret? Our goof? They demanded their money back
	const CHARGEBACKED = 'CHARGEBACKED';
	const REVERSED = 'REVERSED';
	const REFUNDED = 'REFUNDED';

	protected static $statusMap = [
		FinalStatus::CANCELLED => [
			self::CANCELLED,
		],
		FinalStatus::COMPLETE => [
			self::CAPTURED,
			self::PAID,
			self::CAPTURE_REQUESTED,
		],
		FinalStatus::FAILED => [
			self::REJECTED,
			self::REJECTED_CAPTURE,
		],
		FinalStatus::PENDING => [
			self::REDIRECTED,
			self::PENDING_PAYMENT,
			self::AUTHORIZATION_REQUESTED,
		],
		FinalStatus::PENDING_POKE => [
			self::PENDING_APPROVAL,
			self::PENDING_CAPTURE,
			self::PENDING_FRAUD_APPROVAL,
			self::CREATED,
		],
		FinalStatus::REFUNDED => [
			self::CHARGEBACKED,
			self::REVERSED,
			self::REFUNDED,
		]
	];

	public function normalizeStatus( string $ingenicoStatus ): string {
		foreach ( self::$statusMap as $finalStatus => $ingenicoStatuses ) {
			if ( array_search( $ingenicoStatus, $ingenicoStatuses, true ) !== false ) {
				return $finalStatus;
			}
		}
		throw new UnexpectedValueException( "Unknown Ingenico status code $ingenicoStatus" );
	}
}
