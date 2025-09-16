<?php

namespace SmashPig\PaymentProviders\Gravy;

use SmashPig\PaymentData\FinalStatus;
use SmashPig\PaymentData\StatusNormalizer;

class PaymentStatusNormalizer implements StatusNormalizer {

	/**
	 * @param string $paymentProcessorStatus
	 * @return string
	 * @link https://docs.gr4vy.com/guides/api/resources/transactions/statuses
	 */
	public function normalizeStatus( string $status ): string {
		switch ( $status ) {
			case 'authorization_succeeded':
				$normalizedStatus = FinalStatus::PENDING_POKE;
				break;
			case 'processing':
			case 'buyer_approval_pending':
			case 'authorization_void_pending':
			case 'capture_pending':
				$normalizedStatus = FinalStatus::PENDING;
				break;
			case 'authorization_declined':
			case 'authorization_failed':
			case 'failed':
			case 'declined':
			case 'cancelled':
				$normalizedStatus = FinalStatus::FAILED;
				break;
			case 'authorization_voided':
				$normalizedStatus = FinalStatus::CANCELLED;
				break;
			case 'capture_succeeded':
			case 'succeeded':
				$normalizedStatus = FinalStatus::COMPLETE;
				break;
			default:
				throw new \UnexpectedValueException( "Unknown status $status" );
		}

		return $normalizedStatus;
	}
}
