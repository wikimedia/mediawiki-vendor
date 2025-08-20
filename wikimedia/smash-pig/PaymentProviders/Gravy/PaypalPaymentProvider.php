<?php

namespace SmashPig\PaymentProviders\Gravy;

class PaypalPaymentProvider extends PaymentProvider implements IGetPaymentServicePaymentMethodDefinition {
	use GetPaymentServiceDefinitionTrait;

	protected function getPaymentMethod(): string {
		return 'paypal';
	}
}
