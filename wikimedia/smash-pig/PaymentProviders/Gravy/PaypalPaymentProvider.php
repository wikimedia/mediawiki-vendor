<?php

namespace SmashPig\PaymentProviders\Gravy;

use SmashPig\PaymentProviders\Gravy\Mapper\PaypalPaymentProviderRequestMapper;
use SmashPig\PaymentProviders\Gravy\Mapper\RedirectPaymentProviderResponseMapper;
use SmashPig\PaymentProviders\Gravy\Mapper\RequestMapper;
use SmashPig\PaymentProviders\Gravy\Validators\PaymentProviderValidator;
use SmashPig\PaymentProviders\Gravy\Validators\PaypalPaymentProviderValidator;
use SmashPig\PaymentProviders\IPaymentProvider;

class PaypalPaymentProvider extends PaymentProvider implements IPaymentProvider {
	protected function getValidator(): PaymentProviderValidator {
		return new PaypalPaymentProviderValidator();
	}

	protected function getRequestMapper(): RequestMapper {
		return new PaypalPaymentProviderRequestMapper();
	}

	protected function getResponseMapper(): RedirectPaymentProviderResponseMapper {
		return new RedirectPaymentProviderResponseMapper();
	}
}
