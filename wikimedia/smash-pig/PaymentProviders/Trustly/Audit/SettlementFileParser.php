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
 * @see https://www.trustly.com/us/blog/a-merchants-guide-to-ach-returns-and-ach-return-codes
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
			'gateway' => 'gravy',
			'audit_file_gateway' => 'trustly',
			'gateway_txn_id' => $this->getGatewayTxnId(),
			'backend_processor' => 'trustly',
			'backend_processor_txn_id' => $this->row['transaction_id'],
			'date' => strtotime( $this->row['created_at'] ),
			// Arguably the trace_id makes sense here
			'settlement_batch_reference' => $this->row['batch_id'] ?? null,
			'payment_orchestrator_reconciliation_id' => $this->isGravy() ? $this->row['original_merchant_reference'] : null,
			'settled_date' => $this->row['processed_at'] ?? null,
			'settled_fee_amount' => ( $this->row['fee'] ?? null ) ? $this->row['fee'] : 0,
			'settled_net_amount' => ( $this->row['amount'] ?? 0 ) + ( ( $this->row['fee'] ?? null ) ? $this->row['fee'] : 0 ),
			'settled_total_amount' => $this->row['amount'] ?? 0,
			'settled_currency' => $this->row['currency'],
		];
		if ( !empty( $msg['settled_date'] ) ) {
			$msg['settled_date'] = strtotime( $msg['settled_date'] );
		}
		return array_filter( $msg ) + $this->getReversalFields();
	}

	protected function getGatewayTxnId(): string {
		if ( $this->isChargeback() || $this->isRefund() ) {
			// We don't seem to get a gravy transaction ID for these.
			return $this->row['transaction_id'];
		}
		return $this->isGravy() ? Base62Helper::toUuid( $this->row['original_merchant_reference'] ) : $this->row['transaction_id'];
	}

	protected function isGravy(): bool {
		// Checking strlen feels a bit blunt - but it all does.
		// Some refunds seem to bypass gravy. There is precedent for this in the Adyen code.
		return !empty( $this->row['original_merchant_reference'] && strlen( $this->row['original_merchant_reference'] ) < 64 );
	}

	/**
	 * @return array
	 */
	protected function getReversalFields(): array {
		$reversalFields = [];
		if ( $this->isChargeback() || $this->isRefund() ) {
			$reversalFields['type'] = $this->isChargeback() ? 'chargeback' : 'refund';
			if ( $this->isGravy() ) {
				$reversalFields['gateway_parent_id'] = Base62Helper::toUuid( $this->row['original_merchant_reference'] );
				// Doesn't seem to be anything better than this, but it's not 100% clear whose it is.
				$reversalFields['gateway_refund_id'] = $this->row['payment_provider_transaction_id'];
			} else {
				$reversalFields['backend_processor_parent_id'] = $this->row['original_transaction_id'];
				// Doesn't seem to be anything better than this, but it's not 100% clear whose it is.
				$reversalFields['backend_processor_refund_id'] = $this->row['payment_provider_transaction_id'];
			}
		}
		return $reversalFields;
	}

	/**
	 * @return bool
	 */
	protected function isRefund(): bool {
		return $this->row['amount'] < 0 && $this->row['settlement_batch_transaction_type'] === 'Refund';
	}

	/**
	 * @return bool
	 */
	protected function isChargeback(): bool {
		if ( $this->row['reason'] === 'R08' && $this->row['amount'] < 0 && $this->row['settlement_batch_transaction_type'] === 'Return' ) {
			return true;
		}
		// Perhaps the same amount check should apply here too?
		return $this->row['reason'] === 'R10';
	}

}
