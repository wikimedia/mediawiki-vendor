<?php

namespace SmashPig\PaymentData;

/**
 * Classes implementing this interface are used to map status codes returned
 * from payment processor APIs to our internal normalized status codes.
 */
interface StatusNormalizer {
	/**
	 * Maps a payment-processor-specific, potentially API-call-specific response
	 * code to one of our normalized FinalStatus status codes.
	 *
	 * @param string $paymentProcessorStatus
	 * @return string One of the constants defined in FinalStatus
	 */
	public function normalizeStatus( string $paymentProcessorStatus ): string;
}
