<?php

namespace SmashPig\PaymentProviders\Gravy;

use SmashPig\PaymentProviders\Gravy\Mapper\RedirectPaymentProviderRequestMapper;
use SmashPig\PaymentProviders\Gravy\Mapper\RedirectPaymentProviderResponseMapper;
use SmashPig\PaymentProviders\Gravy\Mapper\RequestMapper;
use SmashPig\PaymentProviders\Gravy\Validators\PaymentProviderValidator;
use SmashPig\PaymentProviders\Gravy\Validators\RedirectPaymentProviderValidator;
use SmashPig\PaymentProviders\IPaymentProvider;

class RedirectPaymentProvider extends PaymentProvider implements IPaymentProvider {
	protected function getValidator(): PaymentProviderValidator {
		return new RedirectPaymentProviderValidator();
	}

	protected function getRequestMapper(): RequestMapper {
		return new RedirectPaymentProviderRequestMapper();
	}

	protected function getResponseMapper(): RedirectPaymentProviderResponseMapper {
		return new RedirectPaymentProviderResponseMapper();
	}
}
