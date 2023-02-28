<?php

namespace SmashPig\PaymentProviders\dlocal;

use SmashPig\PaymentData\FinalStatus;
use SmashPig\PaymentData\StatusNormalizer;
use UnexpectedValueException;

class CreatePaymentStatusNormalizer implements StatusNormalizer {

	public const SUCCESS_STATUS = [
		FinalStatus::COMPLETE,
		FinalStatus::PENDING,
		FinalStatus::PENDING_POKE
	];

	/**
	 * @param string $finalStatus
	 * @return bool
	 */
	public function isSuccessStatus( string $finalStatus ): bool {
		return in_array( $finalStatus, self::SUCCESS_STATUS, true );
	}

	/**
	 * @inheritDoc
	 */
	public function normalizeStatus( string $status ): string {
		switch ( $status ) {
			case 'AUTHORIZED':
				$status = FinalStatus::PENDING_POKE;
				break;
			case 'PENDING':
				$status = FinalStatus::PENDING;
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
