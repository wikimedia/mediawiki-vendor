<?php

namespace SmashPig\PaymentProviders\dlocal;

use SmashPig\PaymentData\FinalStatus;
use SmashPig\PaymentData\StatusNormalizer;
use UnexpectedValueException;

class PaymentStatusNormalizer implements StatusNormalizer {

	/**
	 * @var array
	 */
	protected $successStatus = [
		FinalStatus::COMPLETE,
		FinalStatus::PENDING,
		FinalStatus::PENDING_POKE,
	];

	/**
	 * @param string $normalizedStatus
	 * @return bool
	 */
	public function isSuccessStatus( string $normalizedStatus ): bool {
		return in_array( $normalizedStatus, $this->successStatus, true );
	}

	/**
	 * https://docs.dlocal.com/reference/payment-status-codes we have separate subclasses for each type of response
	 * @param string $paymentProcessorStatus
	 * @return string
	 */
	public function normalizeStatus( string $paymentProcessorStatus ): string {
		switch ( $paymentProcessorStatus ) {
			case 'AUTHORIZED':
				$normalizedStatus = FinalStatus::PENDING_POKE;
				break;
			case 'PENDING':
				$normalizedStatus = FinalStatus::PENDING;
				break;
			case 'REJECTED':
				$normalizedStatus = FinalStatus::FAILED;
				break;
			case 'CANCELLED':
				$normalizedStatus = FinalStatus::CANCELLED;
				break;
			case 'VERIFIED':
			case 'SUCCESS':
			case 'PAID':
				$normalizedStatus = FinalStatus::COMPLETE;
				break;
			default:
				throw new UnexpectedValueException( "Unknown status $paymentProcessorStatus" );
		}

		return $normalizedStatus;
	}
}
