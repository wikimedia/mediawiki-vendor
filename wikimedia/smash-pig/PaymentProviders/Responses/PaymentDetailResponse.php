<?php

namespace SmashPig\PaymentProviders\Responses;

use SmashPig\PaymentData\DonorDetails;
use SmashPig\PaymentData\FinalStatus;

/**
 * Represents the status of a payment which may have been created remotely
 *
 * Class PaymentDetailResponse
 * @package SmashPig\PaymentProviders
 */
class PaymentDetailResponse extends PaymentProviderResponse {

	/**
	 * Keys are types of risk scores (e.g. 'cvv' and 'avs') and values are
	 * numbers from 0-100 indicating how likely the authorization is fraudulent.
	 *
	 * @var array
	 */
	protected $riskScores = [];

	/**
	 * @var string|null
	 */
	protected $recurringPaymentToken;

	/**
	 * An identifier for the transaction set by the card network (e.g. Visa, Mastercard).
	 * Some networks require some merchants to store the ID of the initial transaction and
	 * send it back to the processor when charging a recurring installment, per PSD2 SCA.
	 *
	 * @var string|null
	 */
	protected $initialSchemeTransactionId;

	/**
	 * @var string|null
	 */
	protected $processorContactID;

	/**
	 * @var boolean
	 */
	protected $hasDonorDetails = false;

	/**
	 * Child class for saving Donor details
	 *
	 * @var DonorDetails|null
	 */
	protected $donorDetails = null;

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
	public function setRiskScores( array $riskScores ): PaymentDetailResponse {
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
	public function setRecurringPaymentToken( string $recurringPaymentToken ): PaymentDetailResponse {
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
	 * @return PaymentDetailResponse
	 */
	public function setInitialSchemeTransactionId( ?string $schemeTransactionId ): PaymentDetailResponse {
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
	public function setProcessorContactID( ?string $processorContactID ): PaymentDetailResponse {
		$this->processorContactID = $processorContactID;
		return $this;
	}

	/**
	 * @param DonorDetails $donorDetails
	 * @return PaymentDetailResponse
	 */
	public function setDonorDetails( DonorDetails $donorDetails ): PaymentDetailResponse {
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
}
