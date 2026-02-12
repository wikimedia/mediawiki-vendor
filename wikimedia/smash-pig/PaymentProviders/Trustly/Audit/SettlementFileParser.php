<?php
declare( strict_types=1 );

namespace SmashPig\PaymentProviders\Trustly\Audit;

use SmashPig\Core\Helpers\Base62Helper;
use SmashPig\Core\NormalizationException;
use SmashPig\Core\UnhandledException;

/**
 * Parser for Trustly settlement files.
 *
 * @see https://amer.developers.trustly.com/payments/docs/reference-reporting
 */
class SettlementFileParser extends BaseParser {

	/**
	 * Build a normalized recurring message from a Transaction row	.
	 *
	 * @see https://amer.developers.trustly.com/payments/reference/status-codes
	 *
	 * @throws NormalizationException for malformed/unexpected data that should be treated as an error
	 * @throws UnhandledException for rows we intentionally skip (e.g., modify rows)
	 */
	public function getMessage(): array {
		$msg = [
			'currency' => (string)$this->row['currency'],
			'gross' => ( (float)$this->row['amount'] ),
			'gateway' => $this->isGravy() ? 'gravy' : 'trustly',
			'audit_file_gateway' => 'trustly',
			'gateway_txn_id' => $this->getGatewayTxnId(),
			'backend_processor_txn_id' => $this->row['transaction_id'],
			'date' => strtotime( $this->row['created_at'] ),
			// Arguably the trace_id makes sense here
			'settlement_batch_reference' => $this->row['batch_id'] ?? null,
			'payment_orchestrator_reconciliation_id' => $this->row['original_merchant_reference'] ?? null,
			'settled_date' => $this->row['processed_at'] ?? null,
			'settled_fee_amount' => ( $this->row['fee'] ?? null ) ? $this->row['fee'] : 0,
			'settled_net_amount' => ( $this->row['amount'] ?? 0 ) + ( ( $this->row['fee'] ?? null ) ? $this->row['fee'] : 0 ),
			'settled_total_amount' => $this->row['amount'] ?? 0,
			'settled_currency' => $this->row['currency'],
		];
		if ( !empty( $msg['settled_date'] ) ) {
			$msg['settled_date'] = strtotime( $msg['settled_date'] );
		}
		return array_filter( $msg );
	}

	protected function getGatewayTxnId(): string {
		return $this->isGravy() ? Base62Helper::toUuid( $this->row['original_merchant_reference'] ) : $this->row['transaction_id'];
	}

	protected function isGravy(): bool {
		return !empty( $this->row['original_merchant_reference'] );
	}

}
