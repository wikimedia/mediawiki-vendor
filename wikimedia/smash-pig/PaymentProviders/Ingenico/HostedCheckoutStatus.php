<?php

namespace SmashPig\PaymentProviders\Ingenico;

use SmashPig\PaymentData\FinalStatus;
use SmashPig\PaymentData\StatusNormalizer;
use UnexpectedValueException;

/**
 * Normalizes statuses for hosted checkout sessions to our standard FinalStatus constants
 * Note that this is blurring two different concepts on the payment processor side - the
 * session is not the payment.
 * https://epayments-api.developer-ingenico.com/s2sapi/v1/en_US/java/statuses.html?paymentPlatform=GLOBALCOLLECT
 */
class HostedCheckoutStatus implements StatusNormalizer {

	public function normalizeStatus( string $paymentProcessorStatus ): string {
		switch ( $paymentProcessorStatus ) {
			case 'IN_PROGRESS':
				return FinalStatus::PENDING;
			case 'CANCELLED_BY_CONSUMER':
				return FinalStatus::CANCELLED;
			case 'CLIENT_NOT_ELIGIBLE_FOR_SELECTED_PAYMENT_PRODUCT':
				return FinalStatus::FAILED;
			case 'PAYMENT_CREATED':
				// Note that we shouldn't actually use this code path in practice.
				// When a payment is created, the hosted checkout status lookup
				// returns additional information about that payment, including a
				// payment-specific status. That created payment is often in
				// pending-poke by the time we ask about it, but we shouldn't assume.
				throw new UnexpectedValueException(
					'Was asked to normalize ambiguous hosted checkout status PAYMENT_CREATED. ' .
					'Please normalize the status from createdPaymentOutput instead.'
				);
			default:
				throw new UnexpectedValueException(
					"Unexpected hosted checkout session status $paymentProcessorStatus"
				);
		}
	}
}
