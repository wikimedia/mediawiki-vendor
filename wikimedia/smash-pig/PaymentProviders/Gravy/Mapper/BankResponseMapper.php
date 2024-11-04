<?php

namespace SmashPig\PaymentProviders\Gravy\Mapper;

use SmashPig\PaymentData\FinalStatus;

class BankResponseMapper extends ResponseMapper {

	protected function normalizeStatus( string $paymentProcessorStatus ): string {
		if ( $paymentProcessorStatus === "processing" || $paymentProcessorStatus === "capture_pending" ) {
			return FinalStatus::COMPLETE;
		}
		return parent::normalizeStatus( $paymentProcessorStatus );
	}

}
