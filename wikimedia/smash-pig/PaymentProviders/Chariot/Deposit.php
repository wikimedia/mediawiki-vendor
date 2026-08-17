<?php

namespace SmashPig\PaymentProviders\Chariot;

use SmashPig\Core\Helpers\CurrencyRoundingHelper;

class Deposit {
	private array $deposit;

	/**
	 * @var array
	 */
	private array $donations;

	/**
	 * @var array[Donation]
	 */
	private array $donationObjects;

	public function __construct( array $deposit, array $donations = [] ) {
		$this->deposit = $deposit;
		$this->setDonations( $donations );
	}

	public function getDonations(): array {
		return $this->donations;
	}

	public function setDonations( array $donations ): Deposit {
		foreach ( $donations as $donation ) {
			$this->donationObjects[] = new Donation( $donation );
		}
		$this->donations = $donations;
		return $this;
	}

	/**
	 * Get a value from the deposit array at the given path.
	 *
	 * This function also enforces us updating our metadata as to which
	 * fields are used, helping us to in-code document.
	 *
	 * @param string $path
	 * @param mixed|null $default
	 *
	 * @return mixed
	 */
	private function getValue( string $path, mixed $default = null ): mixed {
		ChariotObjectMetadata::assertDepositFieldIsUsed( $path );

		$value = $this->deposit;
		foreach ( explode( '.', $path ) as $key ) {
			if ( !is_array( $value ) || !array_key_exists( $key, $value ) ) {
				return $default;
			}
			$value = $value[$key];
		}

		return $value;
	}

	public function getDeposit(): array {
		return $this->deposit;
	}

	public function getId(): string {
		$id = trim( (string)( $this->getValue( 'id', '' ) ) );
		if ( $id === '' ) {
			throw new \RuntimeException( 'Deposit payload missing id' );
		}
		return $id;
	}

	public function getSettlementBatchReference(): string {
		return preg_replace( '/^deposit_/', '', $this->getId() ) ?: $this->getId();
	}

	public function getCurrency(): string {
		return (string)( $this->deposit['transfer']['currency'] ?? '' );
	}

	public function getPaymentMethod(): string {
		$transfer = $this->deposit['transfer'];
		$typeMap = [
			'inbound_ach_transfer' => 'ACH',
			'check_deposit' => 'Check',
			'inbound_account_transfer' => 'EFT',
		];
		return $typeMap[$transfer['type']];
	}

	public function getCreatedAt(): string {
		return (string)( $this->deposit['created_at'] ?? '' );
	}

	public function getUpdatedAt(): string {
		return (string)( $this->deposit['updated_at'] ?? '' );
	}

	public function getSettledAt(): string {
		return (string)( $this->deposit['settled_at'] ?? '' );
	}

	public function getPaymentSourceId(): string {
		return (string)( $this->deposit['payment_source_id'] ?? '' );
	}

	public function getCheckNumber(): string {
		return (string)( $this->getValue( 'transfer.check_deposit.auxiliary_on_us' ) );
	}

	public function getSettledAmount(): string {
		return CurrencyRoundingHelper::getAmountInMajorUnits(
			$this->getSettledAmountInMinorUnits(),
			$this->getCurrency()
		);
	}

	public function getZeroAmountRounded(): string {
		return CurrencyRoundingHelper::round( 0.0, $this->getCurrency() );
	}

	public function getSettledAmountInMinorUnits(): int {
		return (int)( $this->deposit['transfer']['amount'] ?? 0 );
	}

	/**
	 * Get a deposit timestamp for filenames.
	 *
	 * @return string
	 */
	public function getDepositTimestampForFilename(): string {
		$candidates = [
			$this->getSettledAt(),
			$this->getCreatedAt(),
			$this->getUpdatedAt(),
		];

		foreach ( $candidates as $candidate ) {
			if ( !is_string( $candidate ) || trim( $candidate ) === '' ) {
				continue;
			}
			$timestamp = strtotime( $candidate );
			if ( $timestamp !== false ) {
				return gmdate( 'YmdHis', $timestamp );
			}
		}

		return gmdate( 'YmdHis' );
	}

	/**
	 * Determine the backend processor for a deposit batch.
	 *
	 * @return string
	 */
	public function getBackendProcessor(): string {
		$values = [];

		foreach ( $this->donations as $donation ) {
			if ( !is_array( $donation ) ) {
				continue;
			}
			$platformName = trim( (string)( $donation['platform']['name'] ?? '' ) );
			$orgName = trim( (string)( $donation['donor_advised_fund_grant']['organization_name'] ?? '' ) );

			if ( $platformName !== '' ) {
				$values[] = $platformName;
			} elseif ( $orgName !== '' ) {
				$values[] = $orgName;
			}
		}

		$values = array_values( array_unique( $values ) );
		if ( count( $values ) === 1 ) {
			return $values[0];
		}
		$transfer = is_array( $this->deposit['transfer'] ?? null ) ? $this->deposit['transfer'] : [];
		$ach = is_array( $transfer['inbound_ach_transfer'] ?? null ) ? $transfer['inbound_ach_transfer'] : [];
		return trim( (string)( $ach['originator_company_name'] ?? '' ) );
	}

	/**
	 * Calculate a batch exchange rate from the summed original donation net
	 * amounts and the deposit payout amount.
	 *
	 * @return float
	 */
	public function getExchangeRate(): float {
		$depositNetMinor = $this->getSettledAmount();

		if ( !is_numeric( $depositNetMinor ) ) {
			throw new \RuntimeException( 'Deposit transfer amount is missing or non-numeric' );
		}

		$originalBatchNetMinor = 0.0;
		foreach ( $this->donationObjects as $donationObject ) {
			$net = $donationObject->getOriginalNetAmountRounded();
			if ( is_numeric( $net ) ) {
				$originalBatchNetMinor += (float)$net;
			}
		}

		if ( $originalBatchNetMinor <= 0.0 ) {
			throw new \RuntimeException( 'Cannot calculate exchange rate from zero donation net total' );
		}

		return (float)$depositNetMinor / $originalBatchNetMinor;
	}

	/**
	 * Build an output filename.
	 *
	 * @param string $prefix
	 * @param string $extension
	 *
	 * @return string
	 */
	public function buildFilename( string $prefix, string $extension ): string {
		$parts = [];
		if ( $prefix !== '' ) {
			$parts[] = $prefix;
		}
		$parts[] = $this->getDepositTimestampForFilename();
		$parts[] = $this->getFileSuffix();

		$base = implode( '-', array_filter( $parts, static fn ( string $part ): bool => $part !== '' ) );
		$base = preg_replace( '/[^A-Za-z0-9._-]+/', '_', $base );
		$base = trim( (string)$base, '_-' );

		return $base . '.' . $extension;
	}

	/**
	 * Get the suffix to use for the various output files related to this deposit.
	 *
	 * @return string
	 */
	public function getFileSuffix(): string {
		$parts = [];

		$backendProcessor = $this->getBackendProcessor();
		if ( $backendProcessor !== '' ) {
			$parts[] = $backendProcessor;
		}

		$parts[] = $this->getSettledAmountInMinorUnits();
		$parts[] = $this->getId();

		return implode( '-', $parts );
	}

}
