<?php

namespace SmashPig\PaymentProviders\dlocal;

use SmashPig\PaymentProviders\IPaymentProvider;
use SmashPig\PaymentProviders\Responses\ApprovePaymentResponse;
use SmashPig\PaymentProviders\Responses\CreatePaymentResponse;

class PaymentProvider implements IPaymentProvider {

	public function createPayment( array $params ): CreatePaymentResponse {
		// TODO: Implement createPayment() method.
	}

	public function approvePayment( array $params ): ApprovePaymentResponse {
		// TODO: Implement approvePayment() method.
	}
}
