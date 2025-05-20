<?php

namespace SmashPig\PaymentProviders\Gravy;

use SmashPig\PaymentProviders\Gravy\Mapper\BankPaymentProviderResponseMapper;
use SmashPig\PaymentProviders\Gravy\Mapper\DirectDepositPaymentProviderRequestMapper;
use SmashPig\PaymentProviders\Gravy\Mapper\RequestMapper;
use SmashPig\PaymentProviders\Gravy\Validators\DirectDepositPaymentProviderValidator;
use SmashPig\PaymentProviders\Gravy\Validators\PaymentProviderValidator;
use SmashPig\PaymentProviders\IPaymentProvider;

class DirectDepositPaymentProvider extends PaymentProvider implements IPaymentProvider {
	protected function getValidator(): PaymentProviderValidator {
		return new DirectDepositPaymentProviderValidator();
	}

	protected function getResponseMapper(): BankPaymentProviderResponseMapper {
		return new BankPaymentProviderResponseMapper();
	}

	protected function getRequestMapper(): RequestMapper {
		return new DirectDepositPaymentProviderRequestMapper();
	}
}
