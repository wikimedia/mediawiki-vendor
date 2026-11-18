<?php

namespace SmashPig\PaymentProviders\Responses;

/**
 * Class PaymentMethodResponse
 * @package SmashPig\PaymentProviders
 * TODO: put the normalized list of payment methods here
 */
class PaymentMethodResponse extends PaymentProviderResponse {
	private array $supportedCurrencies = [];

	private array $supportedCountries = [];

	private array $requiredFields = [];

	private array $paymentMethods = [];

	public function setSupportedCurrencies( array $currencies ): static {
		$this->supportedCurrencies = $currencies;
		return $this;
	}

	public function getSupportedCurrencies(): array {
		return $this->supportedCurrencies;
	}

	public function setSupportedCountries( array $countries ): static {
		$this->supportedCountries = $countries;
		return $this;
	}

	public function getSupportedCountries(): array {
		return $this->supportedCountries;
	}

	public function setRequiredFields( array $requiredFields ): static {
		$this->requiredFields = $requiredFields;
		return $this;
	}

	public function getRequiredFields(): array {
		return $this->requiredFields;
	}

	public function getPaymentMethods(): array {
		return $this->paymentMethods;
	}

	public function setPaymentMethods( array $paymentMethods ): static {
		$this->paymentMethods = $paymentMethods;
		return $this;
	}
}
