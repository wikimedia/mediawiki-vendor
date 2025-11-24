<?php

namespace SmashPig\PaymentProviders;

interface IDeleteRecurringPaymentTokenProvider {
	/**
	 * @param array $params
	 * @return bool
	 */
	public function deleteRecurringPaymentToken( array $params ): bool;
}
