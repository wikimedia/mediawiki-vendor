<?php

namespace SmashPig\PaymentProviders\PayPal;

use SmashPig\PaymentData\FinalStatus;
use UnexpectedValueException;

/**
 * Note: This normalizer does not implement the usual interface because it needs
 * to take the full $rawResponse array into consideration - just the raw status
 * is not enough to distinguish between a payment waiting for our approvePayment
 * and a payment where the donor has not yet clicked 'Complete Purchase'.
 */
class ExpressCheckoutStatus {

	public function normalizeStatus( array $rawResponse ): string {
		$paymentProcessorStatus = $rawResponse['CHECKOUTSTATUS'];
		switch ( $paymentProcessorStatus ) {
			case 'PaymentActionNotInitiated':
				if ( empty( $rawResponse['PAYERID'] ) ) {
					// Donor has not clicked 'Complete Purchase'
					return FinalStatus::TIMEOUT;
				} else {
					// Apparently they mean WE have not initiated the payment action -
					// if there is a PAYERID value we can proceed to approvePayment.
					return FinalStatus::PENDING_POKE;
				}
			case 'PaymentActionFailed':
				return FinalStatus::FAILED;
			case 'PaymentActionInProgress':
				return FinalStatus::PENDING_POKE;
			case 'PaymentActionCompleted':
				return FinalStatus::COMPLETE;
			default:
				throw new UnexpectedValueException( "Unexpected CHECKOUTSTATUS $paymentProcessorStatus" );
		}
	}
}
