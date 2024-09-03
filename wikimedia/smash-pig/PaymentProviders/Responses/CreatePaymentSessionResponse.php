<?php

namespace SmashPig\PaymentProviders\Responses;

class CreatePaymentSessionResponse extends PaymentProviderResponse {

	use RedirectResponseTrait;

	/**
	 * Session identifier as returned from the processor, containing all the necessary
	 * data to start making payment. Maybe be a short alphanumeric ID or a JSON blob.
	 * @var string
	 */
	protected string $paymentSession;

	/**
	 * @return string
	 */
	public function getPaymentSession(): string {
		return $this->paymentSession;
	}

	/**
	 * @param string $paymentSession
	 * @return CreatePaymentSessionResponse
	 */
	public function setPaymentSession( string $paymentSession ): CreatePaymentSessionResponse {
		$this->paymentSession = $paymentSession;
		return $this;
	}

}
