<?php

namespace SmashPig\PaymentData;

class PaymentMethod {
	public const APPLE = 'apple';
	public const BT = 'bt';
	public const CASH = 'cash';
	public const CC = 'cc';
	public const DD = 'dd';
	public const EW = 'ew';
	public const GOOGLE = 'google';
	public const PAYPAL = 'paypal';
	public const RTBT = 'rtbt';
	public const STRIPE = 'stripe';
	public const VENMO = 'venmo';
	/* The `stripetoken` payment method is only sent in the response for the charge on a migrated token
	 * as such its only used in the Reference data class to map the label to a payment method.
	 */
	public const STRIPETOKEN = 'stripetoken';
}
