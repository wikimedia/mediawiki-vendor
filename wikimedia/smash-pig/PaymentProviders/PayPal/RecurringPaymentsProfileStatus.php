<?php

namespace SmashPig\PaymentProviders\PayPal;

use SmashPig\PaymentData\FinalStatus;
use SmashPig\PaymentData\StatusNormalizer;
use UnexpectedValueException;

class RecurringPaymentsProfileStatus implements StatusNormalizer {

	public function normalizeStatus( string $recurringPaymentsProfileStatus ): string {
		switch ( $recurringPaymentsProfileStatus ) {
			case 'PendingProfile':
				return FinalStatus::PENDING; // The system is in the process of creating the recurring payment profile. Please check your IPN messages for an update.
			case 'ActiveProfile':
				return FinalStatus::COMPLETE;
			default:
				throw new UnexpectedValueException( "Unexpected PROFILESTATUS $recurringPaymentsProfileStatus" );
		}
	}
}
