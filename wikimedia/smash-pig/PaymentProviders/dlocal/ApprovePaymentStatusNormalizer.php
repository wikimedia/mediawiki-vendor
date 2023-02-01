<?php

namespace SmashPig\PaymentProviders\dlocal;

use SmashPig\PaymentData\FinalStatus;
use SmashPig\PaymentData\StatusNormalizer;
use UnexpectedValueException;

class ApprovePaymentStatusNormalizer implements StatusNormalizer {

	public const SUCCESS_STATUS = [ FinalStatus::COMPLETE ];

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
			case 'PAID':
				$normalizedStatus = FinalStatus::COMPLETE;
				break;
			default:
				throw new UnexpectedValueException( "Unknown status $paymentProcessorStatus" );
		}

		return $normalizedStatus;
	}
}
