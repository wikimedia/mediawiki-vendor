<?php
declare( strict_types=1 );

namespace SmashPig\PaymentProviders\dlocal\Audit;

use SmashPig\Core\Helpers\Base62Helper;
use SmashPig\Core\Helpers\CurrencyRoundingHelper;
use SmashPig\Core\UtcDate;

/**
 * Parser class for the newer Settlement style files.
 *
 * e.g Wikimedia_Settlement_Reports_20260110.csv
 */
class SettlementFileParser extends BaseParser {

	public function parse(): array {
		if ( $this->row['ROW_TYPE'] === 'ADJUSTMENT' || $this->row['ROW_TYPE'] === 'SETTLEMENT_FEE' ) {
			return $this->getFeeMessage();
		}
		$isGravy = $this->isGravy();

		$message = [
			'gateway_txn_id' => $isGravy ? Base62Helper::toUuid( $this->row['TRANSACTION_ID'] ) : $this->row['DLOCAL_TRANSACTION_ID'],
			'gateway' => $isGravy ? 'gravy' : 'dlocal',
			'audit_file_gateway' => 'dlocal',
			'date' => UtcDate::getUtcTimestamp( $this->row['CREATION_DATE'] ),
			'settled_date' => $this->getSettledTimeStamp(),
			'settlement_batch_reference' => $this->getBatchReference(),
			'original_total_amount' => $this->getOriginalTotalAmount(),
			'original_net_amount' => $this->getOriginalNetAmount(),
			'original_fee_amount' => $this->getOriginalFeeAmount(),
			'settled_total_amount' => $this->getSettledTotalAmount(),
			'settled_net_amount' => $this->getSettledNetAmount(),
			'settled_fee_amount' => $this->getSettledFeeAmount(),
			'gross' => $this->getOriginalTotalAmount(),
			'fee' => CurrencyRoundingHelper::round( -$this->getOriginalFeeAmount(), $this->getOriginalCurrency() ),
			'exchange_rate' => round( 1 / $this->row['FX_RATE'], 6 ),
			'settled_currency' => $this->getSettledCurrency(),
			'currency' => $this->row['LOCAL_CURRENCY'],
			'original_currency' => $this->row['LOCAL_CURRENCY'],
			'order_id' => $this->getOrderID(),
			'email' => $this->row['USER_EMAIL'],
			'contribution_tracking_id' => $this->getContributionTrackingId(),
		];
		return $message + $this->getGravyFields() + $this->getReversalFields();
	}

	/**
	 * @return array
	 */
	protected function getGravyFields(): array {
		$gravyFields = [];
		if ( $this->isGravy() ) {
			$gravyFields['backend_processor_txn_id'] = $this->row['DLOCAL_TRANSACTION_ID'];
			$gravyFields['backend_processor'] = 'dlocal';
			$gravyFields['payment_orchestrator_reconciliation_id'] = $this->row['TRANSACTION_ID'];
		}
		return $gravyFields;
	}

	protected function isGravy(): bool {
		return $this->isFromOrchestrator( $this->row['TRANSACTION_ID'] );
	}

	/**
	 * @return array
	 */
	public function getFeeMessage(): array {
		return [
			'settled_date' => UtcDate::getUtcTimestamp( $this->headers['TRANSFER_DATE'] ),
			'date' => UtcDate::getUtcTimestamp( $this->headers['TRANSFER_DATE'] ),
			'gateway' => 'dlocal',
			'audit_file_gateway' => 'dlocal',
			'type' => 'fee',
			'gateway_txn_id' => $this->row['DLOCAL_TRANSACTION_ID'],
			'invoice_id' => $this->row['DLOCAL_TRANSACTION_ID'],
			'settlement_batch_reference' => $this->getBatchReference(),
			// In this context the total amount is what is paid by the donor - ie nothing.
			// The net_amount is what is paid to us - ie a negative value equal to the fee_amount.
			'settled_total_amount' => '0.0',
			// We don't care that some of it shows as fee & some as net in their source - just what
			// we are charged.
			'settled_fee_amount' => CurrencyRoundingHelper::round( (float)$this->row['NET_AMOUNT'], $this->getSettledCurrency() ),
			'settled_net_amount' => CurrencyRoundingHelper::round( (float)$this->row['NET_AMOUNT'], $this->getSettledCurrency() ),
			'settled_currency' => 'USD',
		];
	}

	/**
	 * @return int
	 */
	protected function getSettledTimeStamp(): int {
		return UtcDate::getUtcTimestamp( $this->row['APPROVED_DATE'] );
	}

	/**
	 * @return string
	 */
	protected function getBatchReference(): string {
		return str_replace( [ '/', '-' ], '', $this->headers['TRANSFER_DATE'] );
	}

	/**
	 * @return string
	 */
	public function getOriginalFeeAmount(): string {
		return CurrencyRoundingHelper::round( $this->row['FX_RATE'] * $this->row['FEE_AMOUNT'], $this->getOriginalCurrency() );
	}

	/**
	 * @return string
	 */
	protected function getOriginalCurrency(): string {
		return $this->row['LOCAL_CURRENCY'];
	}

	/**
	 * @return string
	 */
	protected function getSettledCurrency(): string {
		return 'USD';
	}

	/**
	 * @return string
	 */
	protected function getSettledTotalAmount(): string {
		return CurrencyRoundingHelper::round( (float)$this->row['GROSS_AMOUNT'], $this->getSettledCurrency() );
	}

	/**
	 * @return string
	 */
	protected function getSettledNetAmount(): string {
		return CurrencyRoundingHelper::round( (float)$this->row['NET_AMOUNT'], $this->getSettledCurrency() );
	}

	/**
	 * Get the settled fee amount.
	 *
	 * We base this on the net amount less the total amount because the figures are
	 * 2 six decimal place. We want to make sure rounding errors do not cause them
	 * to not add up.
	 *
	 * @return string
	 */
	protected function getSettledFeeAmount(): string {
		return CurrencyRoundingHelper::round( ( (float)$this->getSettledNetAmount() - (float)$this->getSettledTotalAmount() ), $this->getSettledCurrency() );
	}

	/**
	 * @return string
	 */
	protected function getOriginalTotalAmount(): string {
		return CurrencyRoundingHelper::round( (float)$this->row['LOCAL_AMOUNT'], $this->getOriginalCurrency() );
	}

	/**
	 * @return string
	 */
	protected function getOriginalNetAmount(): string {
		return CurrencyRoundingHelper::round( (float)$this->getOriginalTotalAmount() + (float)$this->getOriginalFeeAmount(), $this->getOriginalCurrency() );
	}

}
