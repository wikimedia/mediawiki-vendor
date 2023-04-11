<?php
namespace SmashPig\PaymentProviders\dlocal;

use SmashPig\PaymentProviders\Responses\CancelPaymentResponse;
use SmashPig\PaymentProviders\Responses\IPaymentResponseFactory;
use SmashPig\PaymentProviders\Responses\PaymentProviderResponse;

class DlocalCancelPaymentResponseFactory extends DlocalPaymentResponseFactory implements IPaymentResponseFactory {

	protected static function createBasicResponse(): PaymentProviderResponse {
		return new CancelPaymentResponse();
	}

	protected static function getStatusNormalizer(): PaymentStatusNormalizer {
		return new CancelPaymentStatusNormalizer();
	}

}
