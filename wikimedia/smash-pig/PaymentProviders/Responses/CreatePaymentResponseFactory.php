<?php

namespace SmashPig\PaymentProviders\Responses;

abstract class CreatePaymentResponseFactory {
	/**
	 * @param mixed $rawResponse API response from processor
	 * @return CreatePaymentResponse
	 */
	abstract public static function fromRawResponse( $rawResponse ): CreatePaymentResponse;
}
