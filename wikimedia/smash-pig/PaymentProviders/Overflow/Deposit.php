<?php

namespace SmashPig\PaymentProviders\Overflow;

use SmashPig\Core\Helpers\CurrencyRoundingHelper;

class Deposit {

	private array $deposit;
	private array $summary;
	private array $contributions = [];
	private string $gatewayAccount;

	public function __construct( array $data ) {
		$this->deposit = $data['deposit'];
		$this->summary = $data['summary']['data'];
		$this->gatewayAccount = (string)( $data['gateway_account'] ?? '' );

		foreach ( $data['contributions'] as $item ) {
			$this->contributions[] = new Contribution(
				$item['contribution'],
				$item['lineItem']
			);
		}
	}

	public function getGatewayAccount(): string {
		return $this->gatewayAccount;
	}

	public function getDeposit(): array {
		return $this->deposit;
	}

	public function getSummary(): array {
		return $this->summary;
	}

	public function getContributions(): array {
		return $this->contributions;
	}

	public function getId(): string {
		$id = trim( (string)( $this->deposit['id'] ?? '' ) );
		if ( $id === '' ) {
			throw new \RuntimeException( 'Deposit payload missing id' );
		}
		return $id;
	}

	public function getStatus(): string {
		return (string)( $this->deposit['status'] ?? '' );
	}

	public function getType(): string {
		return (string)( $this->deposit['type'] ?? '' );
	}

	public function getAmountInMinorUnits(): int {
		return (int)( $this->deposit['amountInCents'] ?? 0 );
	}

	public function getAmount(): string {
		return CurrencyRoundingHelper::getAmountInMajorUnits(
			$this->getAmountInMinorUnits(),
			'USD'
		);
	}

	public function getArrivalAt(): string {
		return (string)( $this->deposit['arrivalAt'] ?? '' );
	}

	public function getCreatedAt(): string {
		return (string)( $this->deposit['createdAt'] ?? '' );
	}

	public function getUpdatedAt(): string {
		return (string)( $this->deposit['updatedAt'] ?? '' );
	}

	public function getBankName(): string {
		return (string)( $this->deposit['bankName'] ?? '' );
	}

	public function getBankLast4(): string {
		return (string)( $this->deposit['bankLast4'] ?? '' );
	}

	public function getStatementDescriptor(): string {
		return (string)( $this->deposit['statementDescriptor'] ?? '' );
	}

	public function getPaymentMethodTypes(): array {
		return $this->deposit['paymentMethodType'] ?? [];
	}

	public function getLineItems(): array {
		return $this->deposit['lineItems'] ?? [];
	}

	public function getSummaryContributionCount(): int {
		return (int)( $this->summary['contributions']['count'] ?? 0 );
	}

	public function getSummaryGrossAmountInMinorUnits(): int {
		return (int)( $this->summary['contributions']['grossInCents'] ?? 0 );
	}

	public function getSummaryFeeAmountInMinorUnits(): int {
		return (int)( $this->summary['contributions']['feesInCents'] ?? 0 );
	}

	public function getSummaryNetAmountInMinorUnits(): int {
		return (int)( $this->summary['contributions']['totalInCents'] ?? 0 );
	}

	public function getSummaryGrossAmount(): string {
		return CurrencyRoundingHelper::getAmountInMajorUnits(
			$this->getSummaryGrossAmountInMinorUnits(),
			'USD'
		);
	}

	public function getSummaryFeeAmount(): string {
		return CurrencyRoundingHelper::getAmountInMajorUnits(
			$this->getSummaryFeeAmountInMinorUnits(),
			'USD'
		);
	}

	public function getSummaryNetAmount(): string {
		return CurrencyRoundingHelper::getAmountInMajorUnits(
			$this->getSummaryNetAmountInMinorUnits(),
			'USD'
		);
	}

	public function getSettlementBatchReference(): string {
		return $this->getStatementDescriptor();
	}

	public function validate(): void {
		$lineItemTotal = 0;
		$contributionGross = 0;

		foreach ( $this->contributions as $contribution ) {
			$lineItemTotal += $contribution->getLineItemGrossAmountInMinorUnits();
			$contributionGross += $contribution->getAmountInMinorUnits();
		}

		if ( $lineItemTotal !== $this->getAmountInMinorUnits() ) {
			throw new \RuntimeException(
				sprintf(
					'Deposit %s line item total %d does not match deposit amount %d',
					$this->getId(),
					$lineItemTotal,
					$this->getAmountInMinorUnits()
				)
			);
		}

		if ( $contributionGross !== $this->getSummaryGrossAmountInMinorUnits() ) {
			throw new \RuntimeException(
				sprintf(
					'Deposit %s contribution gross %d does not match summary gross %d',
					$this->getId(),
					$contributionGross,
					$this->getSummaryGrossAmountInMinorUnits()
				)
			);
		}

		$calculatedNet = $this->getSummaryGrossAmountInMinorUnits() -
			$this->getSummaryFeeAmountInMinorUnits();

		if ( $calculatedNet !== $this->getSummaryNetAmountInMinorUnits() ) {
			throw new \RuntimeException(
				sprintf(
					'Deposit %s summary gross %d minus fees %d gives %d, but summary total is %d',
					$this->getId(),
					$this->getSummaryGrossAmountInMinorUnits(),
					$this->getSummaryFeeAmountInMinorUnits(),
					$calculatedNet,
					$this->getSummaryNetAmountInMinorUnits()
				)
			);
		}

		if ( $this->getSummaryNetAmountInMinorUnits() !== $this->getAmountInMinorUnits() ) {
			throw new \RuntimeException(
				sprintf(
					'Deposit %s summary total %d does not match deposit amount %d',
					$this->getId(),
					$this->getSummaryNetAmountInMinorUnits(),
					$this->getAmountInMinorUnits()
				)
			);
		}
	}
}
