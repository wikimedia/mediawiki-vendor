<?php

namespace SmashPig\PaymentProviders\Gravy\Factories;

use SmashPig\PaymentProviders\Responses\CancelPaymentResponse;
use SmashPig\PaymentProviders\Responses\PaymentProviderResponse;

class GravyCancelPaymentResponseFactory extends GravyGetLatestPaymentStatusResponseFactory {

	protected static function createBasicResponse(): PaymentProviderResponse {
		return new CancelPaymentResponse();
	}
}
