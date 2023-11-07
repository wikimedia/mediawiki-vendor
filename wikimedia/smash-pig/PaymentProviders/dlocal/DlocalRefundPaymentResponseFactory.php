<?php
namespace SmashPig\PaymentProviders\dlocal;

use SmashPig\PaymentProviders\Responses\IPaymentResponseFactory;
use SmashPig\PaymentProviders\Responses\PaymentProviderResponse;
use SmashPig\PaymentProviders\Responses\RefundPaymentResponse;

class DlocalRefundPaymentResponseFactory extends DlocalPaymentResponseFactory implements IPaymentResponseFactory {

	protected static function createBasicResponse(): PaymentProviderResponse {
		return new RefundPaymentResponse();
	}

	protected static function getStatusNormalizer(): PaymentStatusNormalizer {
		return new RefundPaymentStatusNormalizer();
	}

}
