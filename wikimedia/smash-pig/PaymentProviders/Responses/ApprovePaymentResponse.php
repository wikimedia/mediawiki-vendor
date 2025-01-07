<?php

namespace SmashPig\PaymentProviders\Responses;

/**
 * Class ApprovePaymentResponse
 * @package SmashPig\PaymentProviders
 */
class ApprovePaymentResponse extends PaymentDetailResponse {

	/**
	 * Net amount, may have been converted to settlement currency
	 * @var float|null
	 */
	protected ?float $settledAmount = null;

	/**
	 * Settlement currency
	 * @var string|null
	 */
	protected ?string $settledCurrency = null;

	/**
	 * @var float|null
	 */
	protected ?float $fee = null;

	/**
	 * If not given, fee is assumed to be in the settlement currency
	 * @var string|null
	 */
	protected ?string $feeCurrency = null;

	public function setSettledAmount( ?float $settledAmount ): ApprovePaymentResponse {
		$this->settledAmount = $settledAmount;
		return $this;
	}

	public function getSettledAmount(): ?float {
		return $this->settledAmount;
	}

	public function setSettledCurrency( ?string $settledCurrency ): ApprovePaymentResponse {
		$this->settledCurrency = $settledCurrency;
		return $this;
	}

	public function getSettledCurrency(): ?string {
		return $this->settledCurrency;
	}

	public function setFee( ?float $fee ): ApprovePaymentResponse {
		$this->fee = $fee;
		return $this;
	}

	public function getFee(): ?float {
		return $this->fee;
	}

	public function setFeeCurrency( ?string $feeCurrency ): ApprovePaymentResponse {
		$this->feeCurrency = $feeCurrency;
		return $this;
	}

	public function getFeeCurrency(): ?string {
		return $this->feeCurrency;
	}
}
