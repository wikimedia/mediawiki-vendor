<?php

namespace SmashPig\PaymentProviders\Adyen;

use SmashPig\PaymentData\FinalStatus;
use SmashPig\PaymentData\StatusNormalizer;
use UnexpectedValueException;

/**
 * Status mapper for payment statuses that we look up after
 * a donor has returned from a hosted page.
 */
class RedirectedPaymentStatus implements StatusNormalizer {

	/**
	 * @param string $adyenStatus
	 * @return string
	 */
	public function normalizeStatus( string $adyenStatus ): string {
		switch ( $adyenStatus ) {
			case 'Authorised':
				$status = FinalStatus::COMPLETE;
				break;
			case 'Pending':
			case 'Received':
				$status = FinalStatus::PENDING;
				break;
			case 'Cancelled':
				$status = FinalStatus::CANCELLED;
				break;
			case 'Refused':
				$status = FinalStatus::FAILED;
				break;
			default:
				throw new UnexpectedValueException( "Unknown Adyen status $adyenStatus" );
		}

		return $status;
	}
}
