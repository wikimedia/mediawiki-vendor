<?php

namespace SmashPig\PaymentProviders;

use SmashPig\PaymentProviders\Responses\RefundPaymentResponse;

interface IRefundablePaymentProvider {

	/**
	 * @param array $params
	 * @return RefundPaymentResponse
	 */
	public function refundPayment( array $params ): RefundPaymentResponse;
}
