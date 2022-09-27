<?php

namespace SmashPig\PaymentData;

class SavedPaymentDetails {

	/**
	 * The token or ID to be used with the payment processor
	 * to charge new payments.
	 * @var string
	 */
	protected $token;

	/**
	 * The display name of the saved payment method
	 * @var string|null
	 */
	protected $displayName;

	/**
	 * The high-level category of payment method (cc / rtbt)
	 * @var string|null
	 */
	protected $paymentMethod;

	/**
	 * Card brand (visa/mc/amex) or other specific payment type
	 * @var string|null
	 */
	protected $paymentSubmethod;

	/**
	 * For cards, the two digit expiration month
	 * @var string|null
	 */
	protected $expirationMonth;

	/**
	 * For cards, the last two digits of the expiration year
	 * @var string|null
	 */
	protected $expirationYear;

	/**
	 * For bank accounts, the international ID number
	 * @var string|null
	 */
	protected $iban;

	/**
	 * For cards, the last four digits of the account number
	 * @var string|null
	 */
	protected $cardSummary;

	/**
	 * Full name of the payment method owner
	 * @var string|null
	 */
	protected $ownerName;

	/**
	 * Email address of the payment method owner
	 * @var string|null
	 */
	protected $ownerEmail;

	/**
	 * @return string
	 */
	public function getToken(): string {
		return $this->token;
	}

	/**
	 * @param string $token
	 * @return SavedPaymentDetails
	 */
	public function setToken( string $token ): SavedPaymentDetails {
		$this->token = $token;
		return $this;
	}

	/**
	 * @return string|null
	 */
	public function getDisplayName(): ?string {
		return $this->displayName;
	}

	/**
	 * @param string|null $displayName
	 * @return SavedPaymentDetails
	 */
	public function setDisplayName( ?string $displayName ): SavedPaymentDetails {
		$this->displayName = $displayName;
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
	 * @return SavedPaymentDetails
	 */
	public function setPaymentMethod( ?string $paymentMethod ): SavedPaymentDetails {
		$this->paymentMethod = $paymentMethod;
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
	 * @return SavedPaymentDetails
	 */
	public function setPaymentSubmethod( ?string $paymentSubmethod ): SavedPaymentDetails {
		$this->paymentSubmethod = $paymentSubmethod;
		return $this;
	}

	/**
	 * @return string|null
	 */
	public function getExpirationMonth(): ?string {
		return $this->expirationMonth;
	}

	/**
	 * @param string|null $expirationMonth
	 * @return SavedPaymentDetails
	 */
	public function setExpirationMonth( ?string $expirationMonth ): SavedPaymentDetails {
		$this->expirationMonth = $expirationMonth;
		return $this;
	}

	/**
	 * @return string|null
	 */
	public function getExpirationYear(): ?string {
		return $this->expirationYear;
	}

	/**
	 * @param string|null $expirationYear
	 * @return SavedPaymentDetails
	 */
	public function setExpirationYear( ?string $expirationYear ): SavedPaymentDetails {
		$this->expirationYear = $expirationYear;
		return $this;
	}

	/**
	 * @return string|null
	 */
	public function getIban(): ?string {
		return $this->iban;
	}

	/**
	 * @param string|null $iban
	 * @return SavedPaymentDetails
	 */
	public function setIban( ?string $iban ): SavedPaymentDetails {
		$this->iban = $iban;
		return $this;
	}

	/**
	 * @return string|null
	 */
	public function getCardSummary(): ?string {
		return $this->cardSummary;
	}

	/**
	 * @param string|null $cardSummary
	 * @return SavedPaymentDetails
	 */
	public function setCardSummary( ?string $cardSummary ): SavedPaymentDetails {
		$this->cardSummary = $cardSummary;
		return $this;
	}

	/**
	 * @return string|null
	 */
	public function getOwnerName(): ?string {
		return $this->ownerName;
	}

	/**
	 * @param string|null $ownerName
	 * @return SavedPaymentDetails
	 */
	public function setOwnerName( ?string $ownerName ): SavedPaymentDetails {
		$this->ownerName = $ownerName;
		return $this;
	}

	/**
	 * @return string|null
	 */
	public function getOwnerEmail(): ?string {
		return $this->ownerEmail;
	}

	/**
	 * @param string|null $ownerEmail
	 * @return SavedPaymentDetails
	 */
	public function setOwnerEmail( ?string $ownerEmail ): SavedPaymentDetails {
		$this->ownerEmail = $ownerEmail;
		return $this;
	}

}
