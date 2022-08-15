<?php

namespace SmashPig\PaymentData;

/**
 * Represents the different ways a merchant can use stored or tokenized
 * payment method details to make multiple payments
 */
class RecurringModel {
	/**
	 * @var string Used for one-off or irregular charge schedules
	 */
	public const CARD_ON_FILE = 'CardOnFile';

	/**
	 * @var string Used for regular (i.e. monthly) charges
	 */
	public const SUBSCRIPTION = 'Subscription';
}
