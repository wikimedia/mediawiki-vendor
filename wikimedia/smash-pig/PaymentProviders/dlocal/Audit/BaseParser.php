<?php
declare( strict_types=1 );

namespace SmashPig\PaymentProviders\dLocal\Audit;

class BaseParser {

	protected array $row;
	protected array $headers;

	public function __construct( array $row, array $headers ) {
		$this->row = $row;
		$this->headers = $headers;
	}

	protected function getContributionTrackingId(): string {
		$parts = explode( '.', $this->getOrderId() );
		return $parts[0];
	}

	protected function isFromOrchestrator( $invoice ): bool {
		// ignore gravy transactions, they have no period and contain letters
		return ( !strpos( $invoice, '.' ) && !is_numeric( $invoice ) );
	}

	protected function getOrderId(): string {
		foreach ( [ 'TRANSACTION_ID', 'DESCRIPTION', 'Transaction Invoice', 'Invoice' ] as $field ) {
			$value = trim( (string)( $this->row[$field] ?? '' ) );
			if ( $value === '' ) {
				continue;
			}
			if ( preg_match( '/^[0-9]+(\.[0-9]+)?$/', $value ) === 1 ) {
				return $value;
			}
		}
		return '';
	}

	/**
	 * @return bool
	 */
	protected function isChargeback(): bool {
		return $this->row['ROW_TYPE'] === 'CHARGEBACK';
	}

	protected function isReversalType(): bool {
		// @todo find out what refunds look like & add.
		return $this->isChargeback();
	}

	/**
	 * @return array
	 */
	protected function getReversalFields(): array {
		$reversalFields = [];
		if ( $this->isReversalType() ) {
			// Only handling chargeback for now until we catch a refund to add in.
			$reversalFields['type'] = 'chargeback';
			// All we have to match it is the order ID, so we can't add parent_gateway_id
			// this is the reference for the reversal transaction.
			$reversalFields['gateway_refund_id'] = $this->row['Transaction ID'];
		}
		return $reversalFields;
	}

}
