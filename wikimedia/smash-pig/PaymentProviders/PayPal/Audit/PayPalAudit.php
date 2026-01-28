<?php
declare( strict_types=1 );

namespace SmashPig\PaymentProviders\PayPal\Audit;

use SmashPig\Core\DataFiles\AuditParser;
use SmashPig\Core\IgnoredException;
use SmashPig\Core\Logging\Logger;
use SmashPig\Core\NormalizationException;
use SmashPig\Core\UnhandledException;

/**
 * Class PayPalAudit
 * @package SmashPig\PaymentProviders\PayPal\Audit
 * Processes PayPal's Audit Reports.
 * Sends donations, chargebacks, and refunds to queue.
 * https://developer.paypal.com/docs/reports/sftp-reports/
 */
class PayPalAudit implements AuditParser {

	protected array $fileData = [];

	/**
	 * Line types to parse.
	 *
	 * CH (column headers) are always parsed and generally so are 'SB' Section Body.
	 * However, other header and footer rows are mostly not, with the STL report
	 * being the exception.
	 *
	 * @var array|string[]
	 */
	protected array $lineTypesToParse = [ 'SB' ];
	private string $parserClass;
	private array $headers = [];
	private array $rows = [];
	private array $conversionRows = [];
	private array $payouts = [];
	private array $feeRows = [];

	public function parseFile( string $path ): array {
		$file = fopen( $path, 'r' );
		$filePrefix = strtoupper( substr( basename( $path ), 0, 3 ) );
		if ( $filePrefix === 'STL' ) {
			$this->lineTypesToParse[] = 'RF';
		}
		$possibleClass = __NAMESPACE__ . "\\" . $filePrefix . 'FileParser';
		if ( class_exists( $possibleClass ) ) {
			$this->parserClass = $possibleClass;
		}

		$columnHeaders = null;

		while ( ( $line = fgetcsv( $file, 0 ) ) !== false ) {
			// skip empty lines
			if ( $line === [ null ] ) {
				continue;
			}

			$recordType = $line[0] ?? '';
			// Remove UTF-8 BOM if present
			$recordType = preg_replace( '/^\xEF\xBB\xBF/', '', $recordType );
			$line[0] = $recordType;

			// Capture the real column headers
			if ( $recordType === 'CH' ) {
				$columnHeaders = $line;
				continue;
			}

			// Only process settlement body rows
			if ( !in_array( $recordType, $this->lineTypesToParse, true ) ) {
				if ( str_ends_with( $recordType, 'H' ) ) {
					$this->headers[] = $line;
				}
				continue;
			}

			// Ignore everything until we have headers
			if ( $columnHeaders === null ) {
				continue;
			}

			// Defensively handle mismatched column counts
			if ( $recordType === 'SB' ) {
				if ( count( $line ) !== count( $columnHeaders ) ) {
					Logger::warning(
						'Skipping TRR line: column count mismatch. ' .
						'Expected ' . count( $columnHeaders ) . ' got ' . count( $line )
					);
					continue;
				}

				$row = array_combine( $columnHeaders, $line );
			} else {
				$row = $line;
			}

			if ( $row === false ) {
				Logger::warning( 'Skipping TRR line: array_combine failed' );
				continue;
			}
			// We need to split out all the currency conversion rows before parsing the transactions.
			$transactionType = BaseParser::getTransactionCodes()[$row['Transaction Event Code'] ?? ''] ?? null;
			if ( $transactionType === 'currency_conversion' ) {
				$this->conversionRows[$row['Invoice ID']][] = $row;
			} elseif ( $transactionType === 'withdrawal' ) {
				// This is a payout row. It should be added onto the aggregate row.
				$this->payouts[$row['Gross Transaction Currency']][] = $row['Gross Transaction Amount'];
			} elseif ( $transactionType === 'chargeback_fee' ) {
				$this->feeRows[$row['PayPal Reference ID']] = $row;
			} elseif ( $transactionType === 'fee_reversal' ) {
				$this->feeRows[$row['Invoice ID']] = $row;
			} else {
				$this->rows[] = $row;
			}

		}

		foreach ( $this->rows as $row ) {
			try {
				$this->parseLine( $row );
			} catch ( NormalizationException $ex ) {
				Logger::error( $ex->getMessage() );
			}
		}
		fclose( $file );
		return $this->fileData;
	}

	/**
	 */
	protected function parseLine( $row ): void {
		try {
			$this->fileData[] = $this->getParser( $row )->getMessage();
		} catch ( IgnoredException $e ) {
			return;
		} catch ( UnhandledException $e ) {
			// This might be too noisy but nice to see what is skipped for now.
			Logger::error( $e->getMessage() );
		}
	}

	/**
	 * @param array $row
	 * @return BaseParser|TRRFileParser
	 */
	private function getParser( array $row ): BaseParser {
		if ( isset( $this->parserClass ) ) {
			return new $this->parserClass( $row, $this->headers, $this->conversionRows, $this->payouts, $this->feeRows );
		}
		return new TRRFileParser( $row, $this->headers, $this->conversionRows, $this->payouts, $this->feeRows );
	}

}
