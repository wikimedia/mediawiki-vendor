<?php

namespace SmashPig\PaymentProviders\Responses;

use SmashPig\PaymentData\DonorDetails;
use SmashPig\PaymentData\FinalStatus;

/**
 * Provides extended metadata and details about a payment transaction. In addition
 * to the base properties from PaymentProviderResponse, this class includes fields for
 * risk scoring, recurring payment tokens, donor information, order identifiers,
 * backend processor details, and other provider-specific data.
 *
 * Note: not all payment providers supply or require these extra fields. See T383400 for details.
 */

class PaymentProviderExtendedResponse extends PaymentProviderResponse {

	use RedirectResponseTrait;

	/**
	 * Keys are types of risk scores (e.g. 'cvv' and 'avs') and values are
	 * numbers from 0-100 indicating how likely the authorization is fraudulent.
	 *
	 * @var array
	 */
	protected array $riskScores = [];

	/**
	 * @var string|null
	 */
	protected ?string $recurringPaymentToken = null;

	/**
	 * An identifier for the transaction set by the card network (e.g. Visa, Mastercard).
	 * Some networks require some merchants to store the ID of the initial transaction and
	 * send it back to the processor when charging a recurring installment, per PSD2 SCA.
	 *
	 * @var string|null
	 */
	protected ?string $initialSchemeTransactionId = null;

	/**
	 * @var string|null
	 */
	protected ?string $processorContactID = null;

	/**
	 * @var string|null
	 */
	protected ?string $backendProcessorContactID = null;

	/**
	 * FIXME: unaccessed, should probably just return $this->donorDetails !== null
	 * @var bool
	 */
	protected bool $hasDonorDetails = false;

	/**
	 * Child class for saving Donor details
	 *
	 * @var DonorDetails|null
	 */
	protected ?DonorDetails $donorDetails = null;

	/**
	 * @var float|null
	 */
	protected ?float $amount = null;

	/**
	 * @var string|null
	 */
	protected ?string $currency = null;

	/**
	 * @var string|null
	 */
	protected ?string $paymentSubmethod = null;

	/**
	 * @var string|null
	 */
	protected ?string $paymentMethod = null;

	/**
	 * @var string|null
	 */
	protected ?string $orderId = null;

	/**
	 * @var string|null
	 * When the primary processor is a payment orchestrator, this field has a normalized name of the
	 * processor which the orchestrator used to process the payment.
	 */
	protected ?string $backendProcessor = null;

	/**
	 * @var string|null
	 * When the primary processor is a payment orchestrator, this field has the transaction identifier
	 * at the processor which the orchestrator used to process the payment.
	 */
	protected ?string $backendProcessorTransactionId = null;

	/**
	 * @var string|null
	 * The authorization identifier at the processor which ultimately processed the payment.
	 */
	protected ?string $authID = null;

	/**
	 * @var string|null
	 * The capture identifier at the processor which ultimately processed the payment.
	 */
	protected ?string $captureID = null;

	/**
	 * @var string|null
	 * When submitted via a payment orchestrator, this field contains the cross-system 'reconciliation' ID.
	 */
	protected ?string $paymentOrchestratorReconciliationId = null;

	/**
	 * @var string|null
	 * UUID identifying the specific payment service connection used by the orchestrator
	 * (e.g. a specific Adyen or dLocal connection). Persisted so subsequent recurring
	 * charges can be sent through the same connection. See T422416.
	 */
	protected ?string $paymentServiceID = null;

	/**
	 * @var bool True when created payment has been flagged as suspected fraud by the payment processor
	 */
	protected bool $isSuspectedFraud = false;

	/**
	 * Determines whether the payment is in a status that requires further
	 * action from the merchant to push through. Generally this means a card
	 * payment has been authorized but not yet captured.
	 *
	 * @return bool
	 */
	public function requiresApproval(): bool {
		return $this->getStatus() === FinalStatus::PENDING_POKE;
	}

	/**
	 * @return array
	 */
	public function getRiskScores(): array {
		return $this->riskScores;
	}

	/**
	 * @param array $riskScores
	 * @return static
	 */
	public function setRiskScores( array $riskScores ): static {
		$this->riskScores = $riskScores;
		return $this;
	}

	/**
	 * @return string|null
	 */
	public function getRecurringPaymentToken(): ?string {
		return $this->recurringPaymentToken;
	}

	/**
	 * @param string $recurringPaymentToken
	 * @return static
	 */
	public function setRecurringPaymentToken( string $recurringPaymentToken ): static {
		$this->recurringPaymentToken = $recurringPaymentToken;
		return $this;
	}

	/**
	 * @return string|null
	 */
	public function getInitialSchemeTransactionId(): ?string {
		return $this->initialSchemeTransactionId;
	}

	/**
	 * @param string|null $schemeTransactionId
	 * @return static
	 */
	public function setInitialSchemeTransactionId( ?string $schemeTransactionId ): static {
		$this->initialSchemeTransactionId = $schemeTransactionId;
		return $this;
	}

	/**
	 * @return string|null
	 */
	public function getProcessorContactID(): ?string {
		return $this->processorContactID;
	}

	/**
	 * @param string|null $processorContactID
	 * @return static
	 */
	public function setProcessorContactID( ?string $processorContactID ): static {
		$this->processorContactID = $processorContactID;
		return $this;
	}

	/**
	 * @param DonorDetails $donorDetails
	 * @return static
	 */
	public function setDonorDetails( DonorDetails $donorDetails ): static {
		$this->hasDonorDetails = true;
		$this->donorDetails = $donorDetails;
		return $this;
	}

	/**
	 * @return DonorDetails|null
	 */
	public function getDonorDetails(): ?DonorDetails {
		return $this->donorDetails;
	}

	/**
	 * @return float|null
	 */
	public function getAmount(): ?float {
		return $this->amount;
	}

	/**
	 * @param float|null $amount
	 * @return static
	 */
	public function setAmount( $amount ): static {
		$this->amount = $amount;
		return $this;
	}

	/**
	 * @return string|null
	 */
	public function getCurrency(): ?string {
		return $this->currency;
	}

	/**
	 * @param string|null $currency
	 * @return static
	 */
	public function setCurrency( ?string $currency ): static {
		$this->currency = $currency;
		return $this;
	}

	/**
	 * @return string|null
	 */
	public function getPaymentSubmethod(): ?string {
		return $this->paymentSubmethod;
	}

	/**
	 * @param string|null $paymentSubmethod
	 * @return static
	 */
	public function setPaymentSubmethod( ?string $paymentSubmethod ): static {
		$this->paymentSubmethod = $paymentSubmethod;
		return $this;
	}

	/**
	 * @return string|null
	 */
	public function getPaymentMethod(): ?string {
		return $this->paymentMethod;
	}

	/**
	 * @param string|null $paymentMethod
	 * @return static
	 */
	public function setPaymentMethod( ?string $paymentMethod ): static {
		$this->paymentMethod = $paymentMethod;
		return $this;
	}

	/**
	 * @return string|null
	 */
	public function getOrderId(): ?string {
		return $this->orderId;
	}

	/**
	 * @param string|null $orderId
	 * @return static
	 */
	public function setOrderId( ?string $orderId ): static {
		$this->orderId = $orderId;
		return $this;
	}

	/**
	 * @param string|null $backendProcessor
	 * @return static
	 */
	public function setBackendProcessor( ?string $backendProcessor ): static {
		$this->backendProcessor = $backendProcessor;
		return $this;
	}

	/**
	 * @return string|null
	 */
	public function getBackendProcessor(): ?string {
		return $this->backendProcessor;
	}

	public function setBackendProcessorTransactionId( ?string $backendProcessorTransactionId ): static {
		$this->backendProcessorTransactionId = $backendProcessorTransactionId;
		return $this;
	}

	public function getBackendProcessorTransactionId(): ?string {
		return $this->backendProcessorTransactionId;
	}

	/**
	 * @return string|null
	 */
	public function getBackendProcessorContactID(): ?string {
		return $this->backendProcessorContactID;
	}

	/**
	 * @param string|null $backendProcessorContactID
	 * @return static
	 */
	public function setBackendProcessorContactID( ?string $backendProcessorContactID ): static {
		$this->backendProcessorContactID = $backendProcessorContactID;
		return $this;
	}

	/**
	 * @return string|null
	 */
	public function getAuthID(): ?string {
		return $this->authID;
	}

	/**
	 * @param string|null $authID
	 * @return static
	 */
	public function setAuthID( ?string $authID ): static {
		$this->authID = $authID;
		return $this;
	}

	/**
	 * @return string|null
	 */
	public function getCaptureID(): ?string {
		return $this->captureID;
	}

	/**
	 * @param string|null $captureID
	 * @return static
	 */
	public function setCaptureID( ?string $captureID ): static {
		$this->captureID = $captureID;
		return $this;
	}

	public function getPaymentOrchestratorReconciliationId(): ?string {
		return $this->paymentOrchestratorReconciliationId;
	}

	public function setPaymentOrchestratorReconciliationId( ?string $paymentOrchestratorReconciliationId ): static {
		$this->paymentOrchestratorReconciliationId = $paymentOrchestratorReconciliationId;
		return $this;
	}

	public function getPaymentServiceID(): ?string {
		return $this->paymentServiceID;
	}

	public function setPaymentServiceID( ?string $paymentServiceID ): static {
		$this->paymentServiceID = $paymentServiceID;
		return $this;
	}

	public function isSuspectedFraud(): bool {
		return $this->isSuspectedFraud;
	}

	public function setSuspectedFraud( bool $suspectedFraud ): static {
		$this->isSuspectedFraud = $suspectedFraud;
		return $this;
	}
}
