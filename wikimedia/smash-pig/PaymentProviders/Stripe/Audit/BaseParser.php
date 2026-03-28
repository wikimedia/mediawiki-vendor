<?php

namespace SmashPig\PaymentProviders\Stripe\Audit;

use SmashPig\Core\UtcDate;

// Shared parser logic for Stripe settlement and activity files.
// Stripe field references:
// - Payout reconciliation: https://docs.stripe.com/reports/report-types/payout-reconciliation
// - Balance change from activity: https://docs.stripe.com/reports/report-types/balance-change-from-activity
// - Metadata: https://docs.stripe.com/metadata
//
// Normalized output intentionally stays within the finite set of fields used by
// the other processor audit implementations. Stripe-specific source columns are
// used only as inputs and are not exposed directly in parsed output.
abstract class BaseParser {

	private string $sourceFilePath = '';

	protected array $row;

	public function __construct( array $row ) {
		$this->row = $row;
	}

	abstract protected function getSettledDateFields(): array;

	/**
	 * Normalize a Stripe CSV row into the shared SmashPig audit record shape.
	 *
	 * Key mappings:
	 *  - payment_metadata[external_identifier] -> order_id
	 *  - payment_intent_id -> backend_processor_txn_id
	 *  - backend_processor -> stripe
	 *  - audit_file_gateway -> stripe
	 *
	 * @return array
	 */
	public function normalizeRow(): array {
		$reportingCategory = trim( (string)( $this->row['reporting_category'] ?? '' ) );
		$type = $this->mapType( $reportingCategory );

		$msg = [
			'gateway' => 'gravy',
			'audit_file_gateway' => 'stripe',
			'backend_processor' => 'stripe',
			'gateway_account' => $this->resolveGatewayAccount(),
			'type' => $type,
			'date' => $this->toUtcTimestamp( $this->firstNonEmpty( $this->row['created_utc'] ?? null, $this->row['created'] ?? null ) ),
			'order_id' => $this->getOrderId(),
			'contribution_tracking_id' => $this->getContributionTrackingId(),
			'backend_processor_txn_id' => $this->row['payment_intent_id'] ?: null,
			'payment_method' => $this->row['payment_method_type'] ?: null,
		] + $this->getOriginalCurrencyFields() + $this->getSettlementFields();

		if ( $type === 'refund' || $type === 'chargeback' ) {
			$msg['gateway_parent_id'] = $this->row['payment_intent_id'];
			$msg['gateway_refund_id'] = $this->row['source_id'];
		}

		return array_filter( $msg, static fn ( $value ) => $value !== null && $value !== '' );
	}

	/**
	 * Map Stripe reporting categories to SmashPig audit types.
	 *
	 * Reporting category docs: https://docs.stripe.com/reports/report-types/payout-reconciliation
	 *
	 * @param string $reportingCategory
	 *
	 * @return string
	 */
	protected function mapType( string $reportingCategory ): string {
		switch ( $reportingCategory ) {

			case 'charge':
				return 'donation';

			case 'refund':
				return 'refund';

			case 'dispute':
				return 'chargeback';

			case 'stripe_fee':
			case 'network_cost':
				return 'fee';

			case 'adjustment':
				return 'adjustment';

			case 'payout':
				return 'payout';

			default:
				return 'adjustment';
		}
	}

	protected function firstNonEmpty( ?string ...$values ): ?string {
		foreach ( $values as $value ) {
			if ( $value !== null && trim( $value ) !== '' ) {
				return $value;
			}
		}
		return null;
	}

	private function resolveGatewayAccount(): ?string {
		$candidate = $this->firstNonEmpty( $this->row['gateway_account'] ?? null );
		if ( $candidate !== null ) {
			return $candidate;
		}

		if ( preg_match( '/-(?!to-)([^-]+)-po_[A-Za-z0-9]+\.csv$/', basename( $this->sourceFilePath ), $matches ) ) {
			return $matches[1];
		}

		if ( preg_match( '/-(?!to-)([^-]+)\.csv$/', basename( $this->sourceFilePath ), $matches ) &&
			( str_starts_with( basename( $this->sourceFilePath ), 'payments-' ) || str_starts_with( basename( $this->sourceFilePath ), 'fees-' ) ) ) {
			return $matches[1];
		}

		return null;
	}

	protected function normalizeCurrency( ?string $value ): ?string {
		if ( $value === null || trim( $value ) === '' ) {
			return null;
		}
		return strtoupper( trim( $value ) );
	}

	protected function toUtcTimestamp( ?string $value ): ?int {
		if ( $value === null || trim( $value ) === '' ) {
			return null;
		}
		return UtcDate::getUtcTimestamp( $value );
	}

	public function getOrderId(): string {
		return $this->row['payment_metadata[external_identifier]'];
	}

	public function getContributionTrackingId(): int {
		$orderId = $this->getOrderId();
		$parts = explode( '.', $orderId );
		return (int)$parts[0];
	}

	protected function getOriginalCurrencyFields(): array {
		return [];
	}

	protected function getSettlementFields(): array {
		return [];
	}

}
