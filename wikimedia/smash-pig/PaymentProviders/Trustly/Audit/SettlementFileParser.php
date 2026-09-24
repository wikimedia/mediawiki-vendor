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
			'gateway' => $this->getGateway(),
			'audit_file_gateway' => 'trustly',
			'gateway_txn_id' => $this->getGatewayTxnId(),
			'backend_processor' => 'trustly',
			'backend_processor_txn_id' => $this->row['transaction_id'],
			'date' => strtotime( $this->row['created_at'] ),
			// Arguably the trace_id makes sense here
			'settlement_batch_reference' => $this->row['batch_id'] ?? null,
			'payment_orchestrator_reconciliation_id' => $this->getPaymentOrchestratorReconciliationId(),
			'settled_date' => $this->row['processed_at'] ?? null,
			'settled_fee_amount' => CurrencyRoundingHelper::round( ( $this->row['fee'] ?? null ) ? (float)$this->row['fee'] : 0, $this->row['currency'] ),
			'settled_net_amount' => CurrencyRoundingHelper::round( ( $this->row['amount'] ?? 0 ) + ( ( $this->row['fee'] ?? null ) ? (float)$this->row['fee'] : 0 ), $this->row['currency'] ),
			'settled_total_amount' => CurrencyRoundingHelper::round( (float)( $this->row['amount'] ?? 0 ), $this->row['currency'] ),
			'settled_currency' => $this->row['currency'],
		];
		if ( !empty( $msg['settled_date'] ) ) {
			$msg['settled_date'] = strtotime( $msg['settled_date'] );
		}
		return array_filter( $msg ) + $this->getReversalFields();
	}

	/**
	 * Get the gateway for the transaction.
	 *
	 * After some trial and error it seems we just return gravy.
	 * There are some transactions (odd reversals) that gravy doesn't know about but
	 * we can't really tell from the detail in the report so we have to sort that out higher up.
	 *
	 * I did try to check the original_merchant_reference, but real data proves it doesn't
	 * track gravy-ness reliably in either direction:
	 * - transaction_id 8090501261 (P11KFUN-3618-20260208120000-20260209120000-0001of0001.csv)
	 *   is a genuinely gravy capture with a normal short reference (1BSfLaECoOEP4Fjg4TpnEX),
	 *   but its own AC118 refund - transaction_id 8094565296, original_transaction_id
	 *   8090501261 (P11KFUN-3618-20260216120000-20260217120000-0001of0001.csv) - carries a
	 *   long 64-char hash reference instead, which does not decode.
	 * - transaction_id 8206407324 (original_transaction_id 8049361922,
	 *   P11KFUN-3618-20260811120000-20260812120000-0001of0001.csv), an R01 ACH return, is
	 *   confirmed gravy from the real IPN log
	 *   (public/audit/trustly/incoming/logs/fundraising-misc-20260807.gz.txt: gateway_txn_id
	 *   6bef478f-f60d-4b37-ac06-84fb176f45a2, payment_orchestrator_reconciliation_id
	 *   3HfWrU84VLlXcqGKxdCisk, contribution_recur_id 2619919) - despite an R-code reason.
	 * So we cannot conclude gravy-ness from the reference or the reason code.
	 */
	protected function getGateway(): string {
		return 'gravy';
	}

	protected function getGatewayTxnId(): string {
		return $this->isGravy() ? Base62Helper::toUuid( $this->row['original_merchant_reference'] ) : $this->row['transaction_id'];
	}

	protected function getPaymentOrchestratorReconciliationId(): ?string {
		return $this->isGravy() ? $this->row['original_merchant_reference'] : null;
	}

	protected function isGravy(): bool {
		return $this->hasDecodableGravyReference();
	}

	/**
	 * Does original_merchant_reference actually decode to a valid UUID, as
	 * opposed to e.g. the ~64 char hex hash some AC118 refunds carry instead
	 * (see commit a51dc3da)? Checking real decodability rather than a length
	 * cutoff.
	 */
	protected function hasDecodableGravyReference(): bool {
		$reference = $this->row['original_merchant_reference'] ?? '';
		if ( $reference === '' ) {
			return false;
		}
		try {
			$hex = str_pad( Base62Helper::toHex( $reference ), 32, '0', STR_PAD_LEFT );
		} catch ( \InvalidArgumentException $e ) {
			return false;
		}
		return Base62Helper::isValidHexUuid( $hex );
	}

	/**
	 * Get the id that uniquely identifies the reversal.
	 *
	 * For chargebacks, trace_id is unique to the reversal whereas
	 * transaction_id is shared with the original transaction it reverses.
	 * Refunds are the exception - transaction_id is stable and unique for
	 * those, and it's what the IPN uses to identify them.
	 */
	protected function getBackendProcessorReversalId(): string {
		return $this->isRefund() ? $this->row['transaction_id'] : $this->row['trace_id'];
	}

	/**
	 * @return array
	 */
	protected function getReversalFields(): array {
		$reversalFields = [];
		if ( !$this->isChargeback() && !$this->isRefund() ) {
			return $reversalFields;
		}
		$reversalFields['type'] = $this->isChargeback() ? 'chargeback' : 'refund';
		$reversalFields['backend_processor_reversal_id'] = $this->getBackendProcessorReversalId();
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
	 * Any R-code (ACH return) negative leg is a chargeback - IPN treats
	 * every failed Trustly-backed transaction as a chargeback regardless of
	 * reason code, so we no longer distinguish "dedicated" codes (R08/R10)
	 * from the rest.
	 *
	 * @return bool
	 */
	protected function isChargeback(): bool {
		return $this->row['reason'] !== 'AC118'
			&& $this->row['amount'] < 0
			&& $this->row['settlement_batch_transaction_type'] === 'Return';
	}

}
