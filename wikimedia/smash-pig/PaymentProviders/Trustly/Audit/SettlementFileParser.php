<?php
declare( strict_types=1 );

namespace SmashPig\PaymentProviders\Trustly\Audit;

use SmashPig\Core\Helpers\Base62Helper;
use SmashPig\Core\Helpers\CurrencyRoundingHelper;
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
			'gateway' => $this->isGravy() ? 'gravy' : 'trustly',
			'audit_file_gateway' => 'trustly',
			'gateway_txn_id' => $this->getGatewayTxnId(),
			'backend_processor' => 'trustly',
			'backend_processor_txn_id' => $this->row['transaction_id'],
			'date' => strtotime( $this->row['created_at'] ),
			// Arguably the trace_id makes sense here
			'settlement_batch_reference' => $this->row['batch_id'] ?? null,
			'payment_orchestrator_reconciliation_id' => $this->isGravy() ? $this->row['original_merchant_reference'] : null,
			'settled_date' => $this->row['processed_at'] ?? null,
			'settled_fee_amount' => CurrencyRoundingHelper::round( ( $this->row['fee'] ?? null ) ? (float)$this->row['fee'] : 0, $this->row['currency'] ),
			'settled_net_amount' => CurrencyRoundingHelper::round( ( $this->row['amount'] ?? 0 ) + ( ( $this->row['fee'] ?? null ) ? (float)$this->row['fee'] : 0 ), $this->row['currency'] ),
			'settled_total_amount' => CurrencyRoundingHelper::round( (float)( $this->row['amount'] ?? 0 ), $this->row['currency'] ),
			'settled_currency' => $this->row['currency'],
		];
		if ( !empty( $msg['settled_date'] ) ) {
			$msg['settled_date'] = strtotime( $msg['settled_date'] );
		}
		if ( $this->isReversalReversal() ) {
			$msg['type'] = 'reversal_reversed';
		}
		return array_filter( $msg ) + $this->getReversalFields();
	}

	protected function getGatewayTxnId(): string {
		return $this->isGravy() ? Base62Helper::toUuid( $this->row['original_merchant_reference'] ) : $this->row['transaction_id'];
	}

	protected function isGravy(): bool {
		if ( $this->isReversalReversal() ) {
			return false;
		}
		// Checking strlen feels a bit blunt - but it all does.
		// Some refunds seem to bypass gravy. There is precedent for this in the Adyen code.
		return !empty( $this->row['original_merchant_reference'] && strlen( $this->row['original_merchant_reference'] ) < 64 );
	}

	/**
	 * @return array
	 */
	protected function getReversalFields(): array {
		$reversalFields = [];
		if ( !$this->isChargeback() && !$this->isRefund() && !$this->isReversal() ) {
			return $reversalFields;
		}
		if ( $this->isChargeback() ) {
			$reversalFields['type'] = 'chargeback';
		} elseif ( $this->isRefund() ) {
			$reversalFields['type'] = 'refund';
		} else {
			$reversalFields['type'] = 'reversal';
		}
		$reversalFields['backend_processor_reversal_id'] = $this->row['transaction_id'];
		if ( $this->isGravy() ) {
			$reversalFields['gateway_parent_id'] = Base62Helper::toUuid( $this->row['original_merchant_reference'] );
			// We don't have a gravy ID for this - use the trustly one.
			$reversalFields['gateway_refund_id'] = $this->row['transaction_id'];
		} else {
			$reversalFields['backend_processor_parent_id'] = $this->row['original_transaction_id'];
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
	 * ACH return codes for which we have dedicated chargeback handling below,
	 * as opposed to the generic reversal/reversal_reversed handling applied
	 * to other R codes (see isUnhandledRCode()).
	 */
	private const CHARGEBACK_REASON_CODES = [ 'R08', 'R10' ];

	/**
	 * @return bool
	 */
	protected function isChargeback(): bool {
		if ( !in_array( $this->row['reason'], self::CHARGEBACK_REASON_CODES, true ) ) {
			return false;
		}
		if ( $this->row['reason'] === 'R08' ) {
			// Perhaps the same amount check should apply to R10 too?
			return $this->row['amount'] < 0 && $this->row['settlement_batch_transaction_type'] === 'Return';
		}
		return true;
	}

	/**
	 * Any ACH return code we don't have specific chargeback/refund handling
	 * for, on its negative (Return) leg. Catches things like R03 (no
	 * account/unable to locate account) that otherwise fell through as an
	 * unlabeled settled message with a negative amount.
	 *
	 * @return bool
	 */
	protected function isReversal(): bool {
		return $this->isUnhandledRCode() && $this->row['amount'] < 0;
	}

	/**
	 * The positive-amount counterpart of isReversal() - the Sale leg of the
	 * same event, sharing reason code, batch and transaction_id.
	 *
	 * @return bool
	 */
	protected function isReversalReversal(): bool {
		return $this->isUnhandledRCode() && $this->row['amount'] >= 0;
	}

	/**
	 * @return bool
	 */
	private function isUnhandledRCode(): bool {
		return str_starts_with( (string)( $this->row['reason'] ?? '' ), 'R' )
			&& !in_array( $this->row['reason'], self::CHARGEBACK_REASON_CODES, true )
			&& !$this->isRefund();
	}

}
