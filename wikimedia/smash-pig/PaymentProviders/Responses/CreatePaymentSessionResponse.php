<?php

namespace SmashPig\PaymentProviders\Responses;

class CreatePaymentSessionResponse extends PaymentProviderResponse {

	/**
	 * Session identifier as returned from the processor, containing all the necessary
	 * data to start making payment. Maybe be a short alphanumeric ID or a JSON blob.
	 * @var string
	 */
	protected $paymentSession;

	/**
	 * URL that a user should be redirected to in order to complete the payment
	 *
	 * @var string|null
	 */
	protected $redirectUrl;

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

	/**
	 * @return string|null
	 */
	public function getRedirectUrl(): ?string {
		return $this->redirectUrl;
	}

	/**
	 * @param string $redirectUrl
	 * @return CreatePaymentSessionResponse
	 */
	public function setRedirectUrl( string $redirectUrl ): CreatePaymentSessionResponse {
		$this->redirectUrl = $redirectUrl;
		return $this;
	}

	public function requiresRedirect(): bool {
		return !empty( $this->redirectUrl );
	}
}
