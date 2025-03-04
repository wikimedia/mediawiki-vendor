<?php

namespace SmashPig\PaymentProviders\Responses;

/**
 * Represents a newly-created payment, where the processor is capable of
 * automatically retrying some failed payments.
 *
 * Class CreatePaymentWithProcessorRetryResponse
 * @package SmashPig\PaymentProviders
 */
class CreatePaymentWithProcessorRetryResponse extends CreatePaymentResponse {

	/**
	 * Whether a processor retry is scheduled.
	 * @var bool
	 */
	protected bool $isProcessorRetryScheduled;

	/**
	 * When processor retry is true, record reference.
	 * @var ?string
	 */
	protected ?string $processorRetryRescueReference = null;

	/**
	 * When processor retry stop, record reason.
	 * @var ?string
	 */
	protected ?string $processorRetryRefusalReason = null;

	/**
	 * @param bool $isProcessorRetryScheduled
	 * @return $this
	 */
	public function setIsProcessorRetryScheduled(
		bool $isProcessorRetryScheduled
	): CreatePaymentWithProcessorRetryResponse {
		$this->isProcessorRetryScheduled = $isProcessorRetryScheduled;
		return $this;
	}

	/**
	 * @return bool
	 */
	public function getIsProcessorRetryScheduled(): bool {
		return $this->isProcessorRetryScheduled;
	}

	/**
	 * @param string|null $processorRetryRescueReference
	 * @return $this
	 */
	public function setProcessorRetryRescueReference(
		?string $processorRetryRescueReference ): CreatePaymentWithProcessorRetryResponse {
		$this->processorRetryRescueReference = $processorRetryRescueReference;
		return $this;
	}

	/**
	 * @return string|null
	 */
	public function getProcessorRetryRescueReference(): ?string {
		return $this->processorRetryRescueReference;
	}

	/**
	 * @param string|null $processorRetryRefusalReason
	 * @return $this
	 */
	public function setProcessorRetryRefusalReason(
		?string $processorRetryRefusalReason
	): CreatePaymentWithProcessorRetryResponse {
		$this->processorRetryRefusalReason = $processorRetryRefusalReason;
		return $this;
	}

	/**
	 * @return string|null
	 */
	public function getProcessorRetryRefusalReason(): ?string {
		return $this->processorRetryRefusalReason;
	}
}
