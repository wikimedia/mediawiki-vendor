<?php

namespace SmashPig\PaymentProviders;

interface IRefundablePaymentProvider {

	/**
	 * @param array $params
	 * @return RefundPaymentResponse
	 */
	public function refundPayment( array $params ): RefundPaymentResponse;
}
