<?php

namespace SmashPig\PaymentProviders\Adyen;

use OutOfBoundsException;
use SmashPig\PaymentData\FinalStatus;
use SmashPig\PaymentData\StatusNormalizer;

/**
 * Payment status normalizer for authorizations that need an
 * approval (capture) to actually transfer the funds.
 */
class ApprovalNeededCreatePaymentStatus implements StatusNormalizer {

	/**
	 * @param string $adyenStatus
	 * @return string
	 */
	public function normalizeStatus( string $adyenStatus ): string {
		switch ( $adyenStatus ) {
			case 'Authorised':
			case 'RedirectShopper':
				$status = FinalStatus::PENDING_POKE;
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
