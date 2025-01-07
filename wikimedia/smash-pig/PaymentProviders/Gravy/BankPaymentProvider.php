<?php

namespace SmashPig\PaymentProviders\Gravy;

use SmashPig\PaymentProviders\Gravy\Mapper\BankPaymentProviderRequestMapper;
use SmashPig\PaymentProviders\Gravy\Mapper\BankPaymentProviderResponseMapper;
use SmashPig\PaymentProviders\Gravy\Mapper\RequestMapper;
use SmashPig\PaymentProviders\Gravy\Validators\PaymentProviderValidator;
use SmashPig\PaymentProviders\Gravy\Validators\RedirectPaymentProviderValidator;
use SmashPig\PaymentProviders\IPaymentProvider;

class BankPaymentProvider extends PaymentProvider implements IPaymentProvider {
	protected function getResponseMapper(): BankPaymentProviderResponseMapper {
		return new BankPaymentProviderResponseMapper();
	}

	protected function getRequestMapper(): RequestMapper {
		return new BankPaymentProviderRequestMapper();
	}

	protected function getValidator(): PaymentProviderValidator {
		return new RedirectPaymentProviderValidator();
	}
}
