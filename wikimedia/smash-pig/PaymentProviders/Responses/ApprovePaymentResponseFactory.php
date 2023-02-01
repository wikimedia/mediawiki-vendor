<?php

namespace SmashPig\PaymentProviders\Responses;

abstract class ApprovePaymentResponseFactory {
	/**
	 * @param mixed $rawResponse API response from processor
	 * @return ApprovePaymentResponse
	 */
	abstract public static function fromRawResponse( $rawResponse ): ApprovePaymentResponse;
}
