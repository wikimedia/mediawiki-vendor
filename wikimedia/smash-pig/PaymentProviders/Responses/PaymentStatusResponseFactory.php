<?php

namespace SmashPig\PaymentProviders\Responses;

abstract class PaymentStatusResponseFactory {

	/**
	 * @param mixed $rawResponse
	 * @return PaymentDetailResponse
	 */
	abstract public static function fromRawResponse( $rawResponse ): PaymentDetailResponse;
}
