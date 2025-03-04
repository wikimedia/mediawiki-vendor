<?php

namespace smashpig\PaymentProviders;

interface IDeleteRecurringPaymentTokenProvider {
	/**
	 * @param array $params
	 * @return bool
	 */
	public function deleteRecurringPaymentToken( array $params ): bool;
}
