<?php

namespace SmashPig\PaymentProviders\Gravy\Responses;

use SmashPig\PaymentProviders\Responses\RefundPaymentResponse;

class RefundResponse extends RefundPaymentResponse {

	/**
	 * @var string|null
	 */
	protected $payment_service_refund_id;

	public function getPaymentServiceRefundId(): ?string {
		return $this->payment_service_refund_id;
	}

	/**
	 * @param string $payment_service_refund_id
	 * @return static
	 */
	public function setPaymentServiceRefundId( string $payment_service_refund_id ): self {
		$this->payment_service_refund_id = $payment_service_refund_id;
		return $this;
	}
}
