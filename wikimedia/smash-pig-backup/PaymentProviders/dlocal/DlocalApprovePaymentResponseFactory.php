<?php

namespace SmashPig\PaymentProviders\dlocal;

use SmashPig\PaymentProviders\Responses\ApprovePaymentResponse;
use SmashPig\PaymentProviders\Responses\IPaymentResponseFactory;
use SmashPig\PaymentProviders\Responses\PaymentProviderResponse;

class DlocalApprovePaymentResponseFactory extends DlocalPaymentResponseFactory implements IPaymentResponseFactory {

	protected static function createBasicResponse(): PaymentProviderResponse {
		return new ApprovePaymentResponse();
	}

	protected static function getStatusNormalizer(): PaymentStatusNormalizer {
		return new ApprovePaymentStatusNormalizer();
	}

}
