<?php

namespace SmashPig\PaymentProviders\Adyen;

use OutOfBoundsException;
use SmashPig\PaymentData\FinalStatus;
use SmashPig\PaymentData\StatusNormalizer;

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
			case 'Received':
				$status = FinalStatus::COMPLETE;
				break;
			case 'Pending':
				$status = FinalStatus::PENDING;
				break;
			case 'Cancelled':
				$status = FinalStatus::CANCELLED;
				break;
			case 'Refused':
				$status = FinalStatus::FAILED;
				break;
			default:
				throw new OutOfBoundsException( "Unknown Adyen status $adyenStatus" );
		}

		return $status;
	}
}
