<?php

namespace SmashPig\PaymentProviders\Responses;

interface IPaymentResponseFactory {

	/**
	 * @param array $rawResponse
	 * @return PaymentProviderResponse
	 */
	public static function fromRawResponse( array $rawResponse ): PaymentProviderResponse;
}
