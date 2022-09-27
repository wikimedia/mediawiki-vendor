<?php

namespace SmashPig\PaymentProviders\Adyen;

use SmashPig\PaymentData\FinalStatus;
use SmashPig\PaymentData\StatusNormalizer;
use UnexpectedValueException;

class CancelPaymentStatus implements StatusNormalizer {

	/**
	 * @param string $adyenStatus
	 * @return string
	 */
	public function normalizeStatus( string $adyenStatus ): string {
		switch ( $adyenStatus ) {
			case 'received':
				$status = FinalStatus::CANCELLED;
				break;
			default:
				throw new UnexpectedValueException( "Unknown Adyen status $adyenStatus" );
		}

		return $status;
	}
}
