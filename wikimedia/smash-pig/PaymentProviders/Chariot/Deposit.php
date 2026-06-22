<?php

namespace SmashPig\PaymentProviders\Chariot;

class Deposit {
	private array $deposit;

	public function __construct( array $deposit ) {
		$this->deposit = $deposit;
	}

	public function getDeposit(): array {
		return $this->deposit;
	}

	public function getId(): string {
		$id = trim( (string)( $this->deposit['id'] ?? '' ) );
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

}
