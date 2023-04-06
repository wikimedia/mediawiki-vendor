<?php

namespace SmashPig\PaymentProviders\Responses;

interface IPaymentResponseFactory {

	/**
	 * @param mixed $rawResponse
	 * @return PaymentProviderResponse
	 */
	public static function fromRawResponse( $rawResponse ): PaymentProviderResponse;
}
