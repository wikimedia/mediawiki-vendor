<?php

namespace SmashPig\PaymentProviders;

use SmashPig\PaymentProviders\Responses\ApprovePaymentResponse;
use SmashPig\PaymentProviders\Responses\CreatePaymentResponse;

interface IPaymentProvider {
	/**
	 * @param array $params
	 * @return CreatePaymentResponse
	 */
	public function createPayment( array $params ): CreatePaymentResponse;

	/**
	 * @param array $params
	 * @return ApprovePaymentResponse
	 */
	public function approvePayment( array $params ): ApprovePaymentResponse;
}
