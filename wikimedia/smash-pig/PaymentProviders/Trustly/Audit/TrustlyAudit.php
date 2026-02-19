<?php
declare( strict_types=1 );

namespace SmashPig\PaymentProviders\Trustly\Audit;

use Brick\Money\Money;
use SmashPig\Core\DataFiles\AuditParser;
use SmashPig\Core\IgnoredException;
use SmashPig\Core\Logging\Logger;
use SmashPig\Core\NormalizationException;
use SmashPig\Core\UnhandledException;

/**
 * Class TrustlyAudit
 * @package SmashPig\PaymentProviders\Trustly\Audit
 *
 * Processes Trustly's reconciliation  Reports.
 * Sends donations, chargebacks, and refunds to queue.
 *
 * @see https://amer.developers.trustly.com/payments/docs/reference-reporting
 */
class TrustlyAudit implements AuditParser {

	protected array $fileData = [];

	private array $rows = [];

	private array $totals = [];

	private array $payouts = [];

	/**
	 * Parse an audit file and normalize records
	 *
	 * @param string $path Full path of the file to be parsed
	 * @return array of donation, refund, and chargeback records
	 */
	public function parseFile( string $path ): array {
		$file = fopen( $path, 'r' );
		while ( ( $line = fgetcsv( $file, 0 ) ) !== false ) {
			// skip empty lines
			if ( $line === [ null ] ) {
				continue;
			}

			$recordType = $line[0] ?? '';
			// Remove UTF-8 BOM if present
			$recordType = preg_replace( '/^\xEF\xBB\xBF/', '', $recordType );
			$line[0] = $recordType;

			if ( $recordType === 'H' ) {
				// Header row - imagine - they could have put headers in it...
				$numberOfFiles = explode( 'of', $line[6] );
				// Setting these here for reference as to what data we can get from
				// them, rather than usefulness.
				$fileNumber = $numberOfFiles[0];
				$fileCount = $numberOfFiles[1];
				$startDate = $line[3];
				$endDate = $line[4];
				continue;
			}
			if ( $recordType === 'L' ) {
				$this->totals = [ 'count' => $line[1], 'total' => $line[2], 'settled_record_count' => $line[3], 'settled_total_amount' => $line[4] ?? 0 ];
				continue;
			}
			if ( $recordType === 'T' ) {
				$columnHeaders = [
					'record_type',
					'transaction_id',
					'created_at',
					'original_transaction_id',
					'merchant_id',
					'payment_type',
					'payment_provider_type',
					'payment_provider_id',
					'account_number',
					'merchant_reference',
					'transaction_type',
					'transaction_status',
					'processed_at',
					'currency',
					'amount',
					'recurring_start_date',
					'recurring_end_date',
					'recurring_frequency',
					'recurring_frequency_unit_type',
					'recurring_currency',
					'recurring_amount',
					'recurring_is_automatic',
					'payment_provider_transaction_id',
				];
				$row = array_combine( $columnHeaders, $line );
				$this->rows[] = $row;
			} elseif ( $recordType === 'I' ) {
				$columnHeaders = [
					'record_type',
					'transaction_id',
					'created_at',
					'original_transaction_id',
					'merchant_id',
					'payment_type',
					'payment_provider_type',
					'payment_provider_id',
					'account_number',
					'merchant_reference',
					'transaction_type',
					'transaction_status',
					'updated_at',
					'processed_at',
					'currency',
					'amount',
					'trace_id',
					'reason',
					'batch_id',
					'settlement_batch_transaction_type',
					'original_merchant_reference',
					'payment_provider_transaction_id',
					'fee',
				];
				$row = array_combine( $columnHeaders, $line );
				$this->rows[] = $row;
			} elseif ( $recordType === 'F' ) {
				$this->payouts[$line[3]] = [
					// Do not pass out these 2 values unless we need them as
					// we need to tell the audit class what they are if we do.
					// 'payout_reference' => $line[3],
					// 'funder_name' => $line[1],
					'settled_currency' => $line[4],
					'settled_total_amount' => $line[5],
					'settled_date' => strtotime( $line[6] ),
					'date' => strtotime( $line[6] ),
					'gateway' => 'trustly',
					'audit_file_gateway' => 'trustly',
					'type' => 'payout',
					'gateway_txn_id' => '',
					'invoice_id' => '',
				];
			} else {
				throw new UnhandledException( 'no idea what to do with record type: ' . $recordType );
			}
		}
		foreach ( $this->rows as $row ) {
			try {
				$this->parseLine( $row );
			} catch ( NormalizationException $ex ) {
				Logger::error( $ex->getMessage() );
			}
		}

		foreach ( $this->payouts as $payout ) {
			if ( !$this->totals[$payout['settlement_batch_reference']]->isEqualTo( $payout['settled_total_amount'] ) ) {
				throw new NormalizationException( 'Total amount mismatch : calculated ' . (string)$this->totals[$payout['settlement_batch_reference']] . ' vs actual ' . $payout['settled_total_amount'] . ' difference' . ( $this->totals[$payout['settlement_batch_reference']]->minus( $payout['settled_total_amount'] ) ) );
			}
		}
		fclose( $file );
		return array_merge( $this->fileData, array_values( $this->payouts ) );
	}

	/**
	 */
	protected function parseLine( $row ): void {
		try {
			// When we get more confident we might permit anything with a non-zero amount
			// but opting in what we see for now https://www.trustly.com/us/blog/a-merchants-guide-to-ach-returns-and-ach-return-codes
			if ( in_array( $row['reason'] ?? '', [ 'AC118', 'R10', 'R08' ], true ) && $row['amount'] && $row['amount'] !== '0.00' ) {
				if ( !empty( $row['batch_id'] ) && empty( $this->payouts[$row['trace_id']]['settlement_batch_reference'] ) ) {
					$this->payouts[$row['trace_id']]['settlement_batch_reference'] = $row['batch_id'];
				}
				// For now ONLY deal with AC Settled
				// https://amer.developers.trustly.com/payments/reference/status-codes
				// This means ONLY the P11KFUN not the P11KREC files are processed.
				$result = $this->getParser( $row )->getMessage();
				if ( !empty( $result['settlement_batch_reference'] ) ) {
					if ( !isset( $this->totals[$result['settlement_batch_reference']] ) ) {
						$this->totals[$result['settlement_batch_reference']] = Money::zero( $result['settled_currency'] );
					}
					if ( $result['settled_total_amount'] ) {
						$this->totals[$result['settlement_batch_reference']] = $this->totals[$result['settlement_batch_reference']]->plus( $result['settled_net_amount'] );
					}
				}
				$this->fileData[] = $result;
			}
		} catch ( IgnoredException $e ) {
			return;
		} catch ( UnhandledException $e ) {
			// This might be too noisy but nice to see what is skipped for now.
			Logger::error( $e->getMessage() );
		}
	}

	/**
	 * @param array $row
	 * @return \SmashPig\PaymentProviders\Trustly\Audit\SettlementFileParser
	 */
	private function getParser( array $row ): SettlementFileParser {
		return new SettlementFileParser( $row );
	}
}
