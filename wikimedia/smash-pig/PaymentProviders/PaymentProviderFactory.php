<?php

namespace SmashPig\PaymentProviders;

use SmashPig\Core\Context;

// FIXME: does 'provider' mean Ingenico, Amazon, etc, or does it mean
// an adapter for a specific company and method?
/**
 * Instantiates payment provider classes
 * TODO: standard way to set credentials from config here, instead of
 * making other classes do it
 */
class PaymentProviderFactory {

	public static function getProviderForMethod( $paymentMethod ): IPaymentProvider {
		$config = Context::get()->getProviderConfiguration();
		$node = "payment-provider/$paymentMethod";
		return $config->object( $node );
	}
}
