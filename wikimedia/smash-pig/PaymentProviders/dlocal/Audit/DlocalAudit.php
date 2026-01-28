<?php namespace SmashPig\PaymentProviders\dlocal\Audit;

use SmashPig\Core\DataFiles\AuditParser;
use SmashPig\Core\Helpers\CurrencyRoundingHelper;
use SmashPig\Core\Logging\Logger;
use SmashPig\Core\NormalizationException;

class DlocalAudit implements AuditParser {

	/**
	 * This is overwritten for more recent types of report which get the columns from the csv.
	 *
	 * @var array|string[]
	 */
	protected array $columnHeaders = [
		'Type', // 'Payment' or 'Refund'
		'Creation date', // YYYY-MM-dd HH:mm:ss
		'Settlement date', // same format
		'Reference', // gateway_txn_id
		'Invoice', // ct_id.attempt_num
		'Country',
		'Payment Method', // corresponds to our payment_submethod
		'Payment Method Type', // our payment_method
		'Net Amount (local)',
		'Amount (USD)', // gross, including fee
		'currency', // yup, this one is lower case
		'Status',
		'User Mail',
		// These two fields refer to the original donation for refunds
		'Transaction Reference',
		'Transaction Invoice',
		'Fee', // In USD.  Dlocal's processing fee
		'IOF', // In USD.  Fee for financial transactions in Brazil
		// The IOF is included in Dlocal's fee, but broken out by request
	];

	protected $fileData;

	private array $headerRow = [];

	private $netTotal = 0;

	public function parseFile( string $path ): array {
		$this->fileData = [];
		$file = fopen( $path, 'r' );

		$firstLine = fgets( $file );
		$delimiter = $this->getDelimiter( $firstLine );

		while ( $line = fgetcsv( $file, 0, $delimiter, '"', '\\' ) ) {
			if ( $line[0] === 'HEADER' ) {
				$headerColumns = explode( $delimiter, trim( $firstLine ) );
				$this->headerRow = array_combine( $headerColumns, $line );
				continue;
			}
			if ( $line[0] === 'ROW_TYPE' ) {
				$this->columnHeaders = array_values( $line );
				continue;
			}
			try {
				$this->parseLine( $line );
			} catch ( NormalizationException $ex ) {
				// TODO: actually throw these below
				Logger::error( $ex->getMessage() );
			}
		}
		fclose( $file );

		// The settlement files provide an amount that reflects the amount settled to the bank.
		// However, it is the sum of the amounts at 6 decimal places. Because we need to round each
		// amount to (for USD) 2 places the sum of these can differ so we record an extra 'fee'
		// to get to an exact match.
		$expectedNetTotal = $this->headerRow['NET_TOTAL_AMOUNT'] ?? 0;
		if ( $expectedNetTotal ) {
			$difference = CurrencyRoundingHelper::round( $expectedNetTotal - $this->netTotal, $this->headerRow['SETTLEMENT_CURRENCY'] );
			if ( $difference !== '0.00' ) {
				$values = [
					'ROW_TYPE' => 'ADJUSTMENT',
					'DLOCAL_TRANSACTION_ID' => $this->headerRow['TRANSFER_ID'] . '-rounding',
					'NET_AMOUNT' => $difference,
				];
				$line = [];
				foreach ( $this->columnHeaders as $index => $columnHeader ) {
					$line[$index] = $values[$columnHeader] ?? null;
				}
				$this->parseLine( $line );
			}
		}
		return $this->fileData;
	}

	protected function parseLine( array $line ): void {
		$row = array_combine( $this->columnHeaders, $line );

		$parser = $this->getParser( $row );

		$line = $parser->parse();
		if ( $line ) {
			$this->fileData[] = $line;
			$this->netTotal += ( $line['settled_net_amount'] ?? 0 );
		}
	}

	/**
	 * @param string $firstLine
	 *
	 * @return string|null
	 */
	private function getDelimiter( string $firstLine ): ?string {
		// Possible delimiters to test
		$delimiters = [ ',', ';' ];

		$bestDelimiter = null;
		$maxFields = 0;

		foreach ( $delimiters as $delimiter ) {
			// str_getcsv correctly handles quoted values
			$fields = str_getcsv( $firstLine, $delimiter );

			if ( count( $fields ) > $maxFields ) {
				$maxFields = count( $fields );
				$bestDelimiter = $delimiter;
			}
		}

		return $bestDelimiter;
	}

	/**
	 * @param array $row
	 *
	 * @return \SmashPig\PaymentProviders\dlocal\Audit\ReportFileParser|\SmashPig\PaymentProviders\dlocal\Audit\SettlementFileParser
	 */
	public function getParser( array $row ): SettlementFileParser|ReportFileParser {
		if ( empty( $this->headerRow ) ) {
			$parser = new ReportFileParser( $row, $this->headerRow );
		} else {
			$parser = new SettlementFileParser( $row, $this->headerRow );
		}
		return $parser;
	}

}
