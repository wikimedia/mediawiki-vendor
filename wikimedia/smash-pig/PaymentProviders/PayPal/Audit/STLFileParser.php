<?php
declare( strict_types=1 );

namespace SmashPig\PaymentProviders\PayPal\Audit;

use SmashPig\Core\Helpers\Base62Helper;
use SmashPig\Core\IgnoredException;
use SmashPig\Core\NormalizationException;
use SmashPig\Core\UnhandledException;
use SmashPig\Core\UtcDate;

/**
 * Parser for STL files.
 *
 * Handles Settlement Reports (STL) from PayPal.
 *
 * Rows provide details on transactions that have settled, including the total settled.
 *
 * @see https://developer.paypal.com/docs/reports/sftp-reports/settlement-report/
 */
class STLFileParser extends BaseParser {

	/**
	 * Build a normalized recurring message from a SAR row.
	 *
	 * @throws NormalizationException for malformed/unexpected data that should be treated as an error
	 * @throws UnhandledException for rows we intentionally skip (e.g., modify rows)
	 */
	public function getMessage(): array {
		if ( ( $this->row[0] ?? null ) === 'RF' ) {
			return $this->getAggregateRow();
		}
		$isGravy = $this->isGravy();
		$msg = [
			'gateway_txn_id' => $isGravy ? Base62Helper::toUuid( $this->row['Custom Field'] ) : $this->row['Transaction ID'],
			'gateway' => $isGravy ? 'gravy' : $this->getGateway(),
			'audit_file_gateway' => 'paypal',
			'date' => strtotime( $this->row['Transaction Initiation Date'] ),
			'settled_date' => strtotime( $this->row['Transaction Completion Date'] ),
			'settlement_batch_reference' => $this->getSettlementBatchReference(),
			'original_total_amount' => $this->getOriginalTotalAmount(),
			'original_fee_amount' => $this->getOriginalFeeAmount(),
			'original_net_amount' => $this->getOriginalNetAmount(),
			'settled_total_amount' => $this->getSettledTotalAmount(),
			'settled_fee_amount' => (string)$this->getSettledFeeAmount(),
			'settled_net_amount' => (string)( $this->getSettledNetAmount() ),
			'exchange_rate' => $this->getExchangeRate(),
			'settled_currency' => $this->getSettledCurrency(),
			'gross' => ( (float)$this->row['Gross Transaction Amount'] ) / 100,
			'currency' => $this->row['Gross Transaction Currency'],
			'original_currency' => $this->row['Gross Transaction Currency'],
			'fee' => $this->getFeeAmount(),
			'payment_method' => 'paypal',
			'order_id' => $this->getOrderID(),
			'contribution_tracking_id' => $this->getContributionTrackingId(),
		];

		return $msg + $this->getGravyFields() + $this->getRecurringFields() + $this->getReversalFields();
	}

	/**
	 * Get settlement data from the report footer row (per currency).
	 *
	 * @return array
	 *
	 * @see https://developer.paypal.com/docs/reports/sftp-reports/settlement-report/#report-data
	 */
	public function getAggregateRow(): array {
		$payouts = 0;
		if ( array_key_exists( $this->row[1], $this->payouts ) ) {
			$payouts = array_sum( $this->payouts[ $this->row[1] ] );
		}
		$settledTotalAmount = ( $this->row[2] - $this->row[3] + $this->row[4] - $this->row[5] + $payouts ) / 100;
		if ( !$settledTotalAmount ) {
			throw new IgnoredException( 'Payout is $0, ignore' );
		}
		$exchangeFields = [];
		if ( $payouts / 100 === $settledTotalAmount ) {
			// If we have a currency (e.g. BRL) that is fully converted to another currency in real time
			// and that currency is (USD) then also include the exchange rate (average) as that has been finalised.
			$exchangeRateToUSD = $this->getAverageExchangeRateForCurrency( $this->row[1], 'USD' );
			if ( $exchangeRateToUSD ) {
				$exchangeFields['exchange_rate'] = $exchangeRateToUSD;
			}
		}
		return [
			'settled_currency' => $this->row[1],
			'settled_total_amount' => $settledTotalAmount,
			'gateway' => 'paypal',
			'type' => 'payout',
			'audit_file_gateway' => 'paypal',
			'gateway_txn_id' => str_replace( $this->getBatchSettledDate(), '/', '' ),
			'invoice_id' => str_replace( $this->getBatchSettledDate(), '/', '' ),
			'settlement_batch_reference' => $this->getSettlementBatchReference(),
			// @todo - do we want to convert these dates to UTC? For now we are doing so,
			// even though using the non utc date in the batch ref seems right - this will
			// be easy to see once we try with real data.
			'settled_date' => $this->getBatchSettledTimeStamp(),
			'date' => $this->getBatchSettledTimeStamp(),
		] + $exchangeFields;
	}

	private function getBatchSettledDate(): ?string {
		foreach ( $this->headers as $header ) {
			if ( $header[0] === 'SH' ) {
				return substr( $header[1], 0, 10 );
			}
		}
		// Maybe throw exception except would need some test clean up.
		return null;
	}

	private function getBatchSettledTimeStamp(): ?int {
		return UtcDate::getUtcTimestamp( $this->getBatchSettledDate(), $this->getTimezoneOffset() );
	}

	/**
	 * @return string
	 */
	private function getTimezoneOffset(): string {
		foreach ( $this->headers as $header ) {
			if ( $header[0] === 'SH' ) {
				return substr( $header[1], -5 );
			}
		}
		// Maybe throw exception except would need some test clean up.
		return '';
	}

	/**
	 * @return string
	 */
	private function getSettlementBatchReference(): string {
		return str_replace( '/', '', $this->getBatchSettledDate() );
	}

}
