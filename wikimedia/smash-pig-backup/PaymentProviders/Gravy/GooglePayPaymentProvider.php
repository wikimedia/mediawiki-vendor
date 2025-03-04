<?php
namespace SmashPig\PaymentProviders\Gravy;

use SmashPig\PaymentProviders\Gravy\Mapper\GooglePayPaymentProviderRequestMapper;
use SmashPig\PaymentProviders\Gravy\Mapper\GooglePayPaymentProviderResponseMapper;
use SmashPig\PaymentProviders\Gravy\Validators\GooglePayPaymentProviderValidator;
use SmashPig\PaymentProviders\Gravy\Validators\PaymentProviderValidator;

class GooglePayPaymentProvider extends PaymentProvider {
	protected function getValidator(): PaymentProviderValidator {
		return new GooglePayPaymentProviderValidator();
	}

	protected function getRequestMapper(): GooglePayPaymentProviderRequestMapper {
		return new GooglePayPaymentProviderRequestMapper();
	}

	protected function getResponseMapper(): GooglePayPaymentProviderResponseMapper {
		return new GooglePayPaymentProviderResponseMapper();
	}

}
