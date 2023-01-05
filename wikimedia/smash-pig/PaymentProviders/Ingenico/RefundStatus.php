<?php

namespace SmashPig\PaymentProviders\Ingenico;

use SmashPig\PaymentData\FinalStatus;
use SmashPig\PaymentData\StatusNormalizer;
use UnexpectedValueException;

class RefundStatus implements StatusNormalizer {

	// Refund created
	const CREATED = 'CREATED';
	// We cancelled the refund
	const CANCELLED = 'CANCELLED';
	// Refund rejected
	const REJECTED = 'REJECTED';
	// We need to approve the refund
	const PENDING_APPROVAL = 'PENDING_APPROVAL';
	// Waiting for Ingenico to refund it
	const REFUND_REQUESTED = 'REFUND_REQUESTED';
	// Maps to 'COMPLETED' statusCategory, but does this mean refunded?
	const CAPTURED = 'CAPTURED';
	// Ingenico has successfully refunded the donor's money
	const REFUNDED = 'REFUNDED';

	protected static $statusMap = [
		FinalStatus::CANCELLED => [
			self::CANCELLED,
		],
		FinalStatus::COMPLETE => [
			self::CAPTURED,
		],
		FinalStatus::FAILED => [
			self::REJECTED,
		],
		FinalStatus::PENDING => [
			self::REFUND_REQUESTED,
		],
		FinalStatus::PENDING_POKE => [
			self::PENDING_APPROVAL,
			self::CREATED,
		],
		FinalStatus::REFUNDED => [
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
