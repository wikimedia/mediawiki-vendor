<?php

namespace SmashPig\PaymentProviders\Gravy;

use SmashPig\PaymentProviders\Responses\PaymentMethodResponse;

interface IGetPaymentServicePaymentMethodDefinition {
	/**
	 * Gets the definition of a payment method on Gravy
	 * Currently, only ideal for payment methods with a unique payment service definition
	 * For example - PayPal, Venmo, and Trustly
	 *
	 * This method is the same for all payment methods on Gravy.
	 *
	 * @return PaymentMethodResponse
	 */
	public function getPaymentServiceDefinition(): PaymentMethodResponse;

	/**
	 * Fetches all the enabled services for a particular payment method on Gravy
	 * with more configuration details.
	 *
	 * This method is the same for all payment methods on Gravy.
	 *
	 * @return PaymentMethodResponse
	 */
	public function getPaymentServicesForMethod(): PaymentMethodResponse;
}
