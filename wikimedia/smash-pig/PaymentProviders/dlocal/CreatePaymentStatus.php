<?php

namespace SmashPig\PaymentProviders\dlocal;

use SmashPig\PaymentData\FinalStatus;
use SmashPig\PaymentData\StatusNormalizer;
use UnexpectedValueException;

class CreatePaymentStatus implements StatusNormalizer {

	/**
	 * @inheritDoc
	 */
	public function normalizeStatus( string $status ): string {
		switch ( $status ) {
			case 'PENDING':
			case 'AUTHORIZED':
				$status = FinalStatus::PENDING_POKE;
				break;
			case 'REJECTED':
				$status = FinalStatus::FAILED;
				break;
			case 'CANCELLED':
				$status = FinalStatus::CANCELLED;
				break;
			case 'VERIFIED':
			case 'PAID':
				$status = FinalStatus::COMPLETE;
				break;
			default:
				throw new UnexpectedValueException( "Unknown status $status" );
		}

		return $status;
	}
}
