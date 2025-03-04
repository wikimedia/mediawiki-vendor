<?php

namespace SmashPig\PaymentProviders\Responses;

trait RedirectResponseTrait {

	/**
	 * Data to be passed along with the redirect
	 *
	 * @var array
	 */
	protected array $redirectData = [];

	/**
	 * URL that a user should be redirected to in order to complete the payment
	 *
	 * @var string|null
	 */
	protected ?string $redirectUrl = null;

	/**
	 * @param string $redirectUrl
	 * @return self
	 */
	public function setRedirectUrl( string $redirectUrl ): self {
		$this->redirectUrl = $redirectUrl;
		return $this;
	}

	/**
	 * @return bool
	 */
	public function requiresRedirect(): bool {
		return $this->redirectUrl !== null;
	}

	/**
	 * @return string|null
	 */
	public function getRedirectUrl(): ?string {
		return $this->redirectUrl;
	}

	/**
	 * @return array
	 */
	public function getRedirectData(): array {
		return $this->redirectData;
	}

	/**
	 * @param array $redirectData
	 * @return self
	 */
	public function setRedirectData( array $redirectData ): self {
		$this->redirectData = $redirectData;
		return $this;
	}
}
