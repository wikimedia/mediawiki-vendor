<?php
declare( strict_types=1 );

namespace SmashPig\PaymentProviders\PayPal\Audit;

use SmashPig\Core\Helpers\Base62Helper;
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
			'settlement_batch_reference' => str_replace( '/', '', substr( $this->row['Transaction Completion Date'], 0, 10 ) ),
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
		$settlementDate = $timezoneOffset = '';
		foreach ( $this->headers as $header ) {
			if ( $header[0] === 'SH' ) {
				$settlementDate = substr( $header[1], 0, 10 );
				$timezoneOffset = substr( $header[1], -5 );
			}
		}
		$payouts = 0;
		if ( array_key_exists( $this->row[1], $this->payouts ) ) {
			$payouts = array_sum( $this->payouts[ $this->row[1] ] );
		}
		return [
			'settled_currency' => $this->row[1],
			'settled_total_amount' => ( $this->row[2] - $this->row[3] + $this->row[4] - $this->row[5] + $payouts ) / 100,
			'gateway' => 'paypal',
			'type' => 'payout',
			'gateway_txn_id' => str_replace( $settlementDate, '/', '' ),
			'invoice_id' => str_replace( $settlementDate, '/', '' ),
			'settlement_batch_reference' => str_replace( '/', '', $settlementDate ),
			// @todo - do we want to convert these dates to UTC? For now we are doing so,
			// even though using the non utc date in the batch ref seems right - this will
			// be easy to see once we try with real data.
			'settled_date' => UtcDate::getUtcTimestamp( $settlementDate, $timezoneOffset ),
			'date' => UtcDate::getUtcTimestamp( $settlementDate, $timezoneOffset ),
		];
	}

}
