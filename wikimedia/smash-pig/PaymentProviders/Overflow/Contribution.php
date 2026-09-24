<?php

namespace SmashPig\PaymentProviders\Overflow;

use SmashPig\Core\Helpers\CurrencyRoundingHelper;

class Contribution {

	private array $contribution;
	private array $lineItem;

	public function __construct( array $contribution, array $lineItem = [] ) {
		$this->contribution = $contribution;
		$this->lineItem = $lineItem;
	}

	public function getContribution(): array {
		return $this->contribution;
	}

	public function getLineItem(): array {
		return $this->lineItem;
	}

	public function getId(): string {
		return (string)( $this->contribution['id'] ?? '' );
	}

	public function getType(): string {
		return (string)( $this->contribution['type'] ?? '' );
	}

	public function getStatus(): string {
		return (string)( $this->contribution['status'] ?? '' );
	}

	public function getAmountInMinorUnits(): int {
		return CurrencyRoundingHelper::getAmountInMinorUnits(
			(float)( $this->contribution['amount'] ?? 0 ),
			'USD'
		);
	}

	public function getAmount(): string {
		return CurrencyRoundingHelper::round(
			(float)( $this->contribution['amount'] ?? 0 ),
			'USD'
		);
	}

	public function isDonorCoveredFees(): bool {
		return (bool)( $this->contribution['donorCoveredFees'] ?? false );
	}

	public function getCreatedAt(): string {
		return (string)( $this->contribution['createdAt'] ?? '' );
	}

	public function getUpdatedAt(): string {
		return (string)( $this->contribution['updatedAt'] ?? '' );
	}

	public function getContributionDate(): string {
		return (string)( $this->contribution['contributionDate'] ?? '' );
	}

	public function getContributionReceivedAt(): string {
		return (string)( $this->contribution['contributionReceivedAt'] ?? '' );
	}

	public function getFirstName(): string {
		return (string)( $this->contribution['donor']['firstName'] ?? '' );
	}

	public function getLastName(): string {
		return (string)( $this->contribution['donor']['lastName'] ?? '' );
	}

	public function getEmail(): string {
		return (string)( $this->contribution['donor']['email'] ?? '' );
	}

	public function getPhone(): string {
		return (string)( $this->contribution['donor']['phone'] ?? '' );
	}

	public function getAddress(): array {
		return $this->contribution['donor']['address'] ?? [];
	}

	public function getStreetAddress(): string {
		return (string)( $this->getAddress()['line1'] ?? '' );
	}

	public function getSupplementalAddress(): string {
		return (string)( $this->getAddress()['line2'] ?? '' );
	}

	public function getCity(): string {
		return (string)( $this->getAddress()['city'] ?? '' );
	}

	public function getStateProvince(): string {
		return (string)( $this->getAddress()['state'] ?? '' );
	}

	public function getPostalCode(): string {
		return (string)( $this->getAddress()['zip'] ?? '' );
	}

	public function getCountry(): string {
		return (string)( $this->getAddress()['country'] ?? '' );
	}

	public function isAnonymous(): bool {
		return (bool)( $this->contribution['anonymous'] ?? false );
	}

	public function getLocationId(): string {
		return (string)( $this->contribution['locationId'] ?? '' );
	}

	public function getPaymentMethod(): array {
		return $this->contribution['paymentMethod'] ?? [];
	}

	public function getPaymentMethodType(): string {
		return (string)( $this->contribution['paymentMethod']['type'] ?? '' );
	}

	public function getPaymentMethodLast4(): string {
		return (string)( $this->contribution['paymentMethod']['last4'] ?? '' );
	}

	public function getStockQuantity(): ?float {
		if ( !isset( $this->contribution['stocks']['quantity'] ) ) {
			return null;
		}

		return (float)$this->contribution['stocks']['quantity'];
	}

	public function getStockTickers(): array {
		return $this->contribution['stocks']['tickers'] ?? [];
	}

	public function getGivingLinkId(): ?string {
		return $this->contribution['givingLinkId'] ?? null;
	}

	public function getPledgeId(): ?string {
		return $this->contribution['pledgeId'] ?? null;
	}

	public function getLineItemGrossAmountInMinorUnits(): int {
		return (int)( $this->lineItem['grossValueInCents'] ?? 0 );
	}

	public function getLineItemReferenceId(): string {
		return (string)( $this->lineItem['referenceId'] ?? '' );
	}
}
