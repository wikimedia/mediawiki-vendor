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
	protected $isProcessorRetryScheduled;

	/**
	 * When processor retry stop, record reason.
	 * @var string
	 */
	protected $processorRetryRefusalReason;

	public function setIsProcessorRetryScheduled(
		bool $isProcessorRetryScheduled
	): CreatePaymentWithProcessorRetryResponse {
		$this->isProcessorRetryScheduled = $isProcessorRetryScheduled;
		return $this;
	}

	public function getIsProcessorRetryScheduled(): bool {
		return $this->isProcessorRetryScheduled;
	}

	public function setProcessorRetryRefusalReason(
		string $processorRetryRefusalReason
	): CreatePaymentWithProcessorRetryResponse {
		$this->processorRetryRefusalReason = $processorRetryRefusalReason;
		return $this;
	}

	public function getProcessorRetryRefusalReason(): string {
		return $this->processorRetryRefusalReason;
	}
}
