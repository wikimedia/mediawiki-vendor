<?php

namespace SmashPig\PaymentProviders\dlocal;

use SmashPig\PaymentData\FinalStatus;
use SmashPig\PaymentData\StatusNormalizer;
use UnexpectedValueException;

class CancelPaymentStatusNormalizer implements StatusNormalizer {

	public const SUCCESS_STATUS = [ FinalStatus::CANCELLED ];

	/**
	 * @param string $paymentProcessorStatus
	 * @return bool
	 */
	public function isSuccessStatus( string $paymentProcessorStatus ): bool {
		return in_array( $this->normalizeStatus( $paymentProcessorStatus ), static::SUCCESS_STATUS, true );
	}

	/**
	 * @inheritDoc
	 */
	public function normalizeStatus( string $paymentProcessorStatus ): string {
		switch ( $paymentProcessorStatus ) {
			case 'CANCELLED':
				$normalizedStatus = FinalStatus::CANCELLED;
				break;
			default:
				throw new UnexpectedValueException( "Unknown status $paymentProcessorStatus" );
		}

		return $normalizedStatus;
	}
}
