<?php

namespace SmashPig\PaymentProviders\Braintree;

use SmashPig\PaymentData\FinalStatus;
use SmashPig\PaymentData\StatusNormalizer;
use UnexpectedValueException;

class PaymentStatus implements StatusNormalizer {

	/**
	 * See https://graphql.braintreepayments.com/reference/#enum--paymentstatus
	 * @inheritDoc
	 */
	public function normalizeStatus( string $paymentProcessorStatus ): string {
		switch ( $paymentProcessorStatus ) {
			case 'AUTHORIZED':
				return FinalStatus::PENDING_POKE;
			case 'AUTHORIZATION_EXPIRED':
			case 'FAILED':
			case 'GATEWAY_REJECTED':
			case 'PROCESSOR_DECLINED':
			case 'SETTLEMENT_DECLINED':
				return FinalStatus::FAILED;
			case 'VOIDED':
				return FinalStatus::CANCELLED;
			case 'SETTLED':
			case 'SETTLEMENT_CONFIRMED':
			case 'SETTLING':
			case 'SUBMITTED_FOR_SETTLEMENT':
				return FinalStatus::COMPLETE;
			case 'AUTHORIZING':
			case 'SETTLEMENT_PENDING':
				return FinalStatus::PENDING;
			default:
				throw new UnexpectedValueException(
					"Cannot normalize unknown transaction status $paymentProcessorStatus"
				);
		}
	}
}
