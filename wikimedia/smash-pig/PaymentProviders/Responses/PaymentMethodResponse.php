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

	public function setSupportedCurrencies( array $currencies ): void {
		$this->supportedCurrencies = $currencies;
	}

	public function getSupportedCurrencies(): array {
		return $this->supportedCurrencies;
	}

	public function setSupportedCountries( array $countries ): void {
		$this->supportedCountries = $countries;
	}

	public function getSupportedCountries(): array {
		return $this->supportedCountries;
	}

	public function setRequiredFields( array $requiredFields ): void {
		$this->requiredFields = $requiredFields;
	}

	public function getRequiredFields(): array {
		return $this->requiredFields;
	}

	public function getPaymentMethods(): array {
		return $this->paymentMethods;
	}

	public function setPaymentMethods( array $paymentMethods ): void {
		$this->paymentMethods = $paymentMethods;
	}
}
