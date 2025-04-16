<?php

namespace SmashPig\PaymentProviders\dlocal;

use SmashPig\PaymentProviders\Responses\IPaymentResponseFactory;
use SmashPig\PaymentProviders\Responses\PaymentProviderExtendedResponse;
use SmashPig\PaymentProviders\Responses\PaymentProviderResponse;

class DlocalPaymentStatusResponseFactory extends DlocalPaymentResponseFactory implements IPaymentResponseFactory {

	protected static function createBasicResponse(): PaymentProviderResponse {
		return new PaymentProviderExtendedResponse();
	}

	protected static function getStatusNormalizer(): PaymentStatusNormalizer {
		return new PaymentStatusNormalizer();
	}

}
