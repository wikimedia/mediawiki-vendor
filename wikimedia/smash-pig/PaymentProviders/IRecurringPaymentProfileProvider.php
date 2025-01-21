<?php

namespace SmashPig\PaymentProviders;

use SmashPig\PaymentProviders\Responses\CancelSubscriptionResponse;
use SmashPig\PaymentProviders\Responses\CreateRecurringPaymentsProfileResponse;

/**
 * Marks a payment provider as being able to set up a recurring payments profile,
 * that is, a recurring payment whose schedule is managed by the provider.
 */
interface IRecurringPaymentProfileProvider {
	public function createRecurringPaymentsProfile( array $params ): CreateRecurringPaymentsProfileResponse;

	public function cancelSubscription( array $params ): CancelSubscriptionResponse;
}
