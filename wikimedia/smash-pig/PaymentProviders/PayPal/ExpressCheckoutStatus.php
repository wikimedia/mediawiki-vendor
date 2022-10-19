<?php

namespace SmashPig\PaymentProviders\PayPal;

use SmashPig\PaymentData\FinalStatus;
use SmashPig\PaymentData\StatusNormalizer;
use UnexpectedValueException;

class ExpressCheckoutStatus implements StatusNormalizer {

	public function normalizeStatus( string $paymentProcessorStatus ): string {
		switch ( $paymentProcessorStatus ) {
			case 'PaymentActionNotInitiated':
				return FinalStatus::TIMEOUT; // could be pending? means they haven't signed in
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
