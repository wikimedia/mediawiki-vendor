<?php

namespace SmashPig\PaymentProviders\CheckoutCom\Audit;

use Brick\Math\RoundingMode;
use Brick\Money\Money;
use DateTimeImmutable;
use OutOfBoundsException;
use RuntimeException;
use SmashPig\Core\Logging\Logger;
use SmashPig\Core\SmashPigException;

class CheckoutComAudit {

	private const PAYOUT_SEARCH_DAYS = 3;
	private const MAX_ROUNDING_ADJUSTMENT_PER_ROW = 0.005;
	private float $roundingAdjustment = 0.0;
	private int $roundingRows = 0;

	/**
	 * @var array<int,array<string,mixed>>
	 */
	protected array $fileData = [];

	/**
	 * @var string[]
	 */
	protected array $columnHeaders = [];

	/**
	 * @var string[]
	 */
	protected array $requiredColumns = [
		'Client Entity ID',
		'Client Entity Name',
		'Processing Channel ID',
		'Processing Channel Name',
		'Type',
		'Payment ID',
		'Reference',
		'Processed On',
		'Available On',
		'Holding Currency',
		'Payout ID',
		'Gross In Holding Currency',
		'Deduction In Holding Currency',
		'Net In Holding Currency',
		'Processing Fee In Holding Currency',
		'Scheme Fee In Holding Currency',
		'Interchange In Holding Currency',
		'Tax In Holding Currency',
		'Reserve In Holding Currency',
		'Processing Currency',
		'Gross In Processing Currency',
		'FX Rate Applied',
		'Payment Method',
	];

	/**
	 * @var string[]
	 */
	protected array $requiredPayoutColumns = [
		'Client Entity ID',
		'Client Entity Name',
		'Payout ID',
		'Payout Date',
		'Holding Currency',
		'Payout Amount',
	];

	private array $feeRows = [];
	private array $rows = [];

	/**
	 * @param string $path
	 * @return array<int,array<string,mixed>>
	 */
	public function parseFile( string $path ): array {
		$this->fileData = [];
		$this->roundingAdjustment = 0.0;
		$this->roundingRows = 0;
		$file = fopen( $path, 'r' );

		if ( !$file ) {
			throw new RuntimeException( "Unable to open $path" );
		}

		$this->columnHeaders = fgetcsv( $file, 0, ',', '"', '\\' );
		$missingColumns = array_diff( $this->requiredColumns, $this->columnHeaders );

		if ( count( $missingColumns ) > 0 ) {
			throw new RuntimeException( 'Missing columns ' . implode( ',', $missingColumns ) );
		}

		while ( $line = fgetcsv( $file, 0, ',', '"', '\\' ) ) {

			try {
				$row = array_combine( $this->columnHeaders, $line );
				if ( $this->isFeeRow( $row ) ) {
					$this->feeRows[$row['Reference']][] = $row;
				} else {
					$this->rows[] = $row;
				}
			} catch ( OutOfBoundsException $ex ) {
				Logger::error( $ex->getMessage() );
			}
		}
		fclose( $file );

		foreach ( $this->rows as $row ) {
			$this->parseRow( $row );
		}
		$this->appendUnusedFeeRows();
		$this->appendPayoutTransaction( $path );

		return $this->fileData;
	}

	/**
	 * @param array<int,string|null> $row
	 */
	protected function parseRow( array $row ): void {
		$type = strtolower( $row['Type'] );
		$feeRows = $this->feeRows[$row['Reference']] ?? [];
		unset( $this->feeRows[$row['Reference']] );
		$parser = $this->getParser( $row, $feeRows );

		if ( $this->isDonationRow( $row ) ) {
			$transaction = $parser->parseDonation( $row );
			$this->trackSettlementRounding( $row, $transaction );
			$this->fileData[] = $transaction;
			return;
		}

		if ( $this->isRefundRow( $row ) ) {
			$transaction = $parser->parseRefund( $row, $type );
			$this->trackSettlementRounding( $row, $transaction );
			$this->fileData[] = $transaction;
			return;
		}
		if ( $this->isFeeRow( $row ) ) {
			$transaction = $parser->getFeeTransaction( $row );
			$this->trackSettlementRounding( $row, $transaction );
			$this->fileData[] = $transaction;
			return;
		}
		throw new SmashPigException( 'Unknown row type ' . json_encode( $row ) );
	}

	protected function isDonationRow( array $row ): bool {
		return strtolower( $row['Type'] ?? '' ) === 'charge'
			&& (float)$row['Gross In Processing Currency'] > 0;
	}

	protected function isRefundRow( array $row ): bool {
		return in_array(
			strtolower( $row['Type'] ?? '' ),
			[ 'refund', 'chargeback' ],
			true
		);
	}

	/**
	 * Is this row a fee of some sort.
	 *
	 * Network token, account updater, voids, merchant payout fees,
	 * zero-gross charge rows, and similar settlement-cost rows do not
	 * represent donor payments, but they do affect payout.
	 */
	protected function isFeeRow( array $row ): bool {
		return !$this->isDonationRow( $row ) && !$this->isRefundRow( $row );
	}

	/**
	 * Track the difference between Checkout.com's high-precision settlement net
	 * and the currency-rounded audit row amount. This is used only as a sanity
	 * check for the synthetic batch-level rounding adjustment.
	 *
	 * @param array<string,string|null> $row
	 * @param array<string,mixed> $transaction
	 */
	protected function trackSettlementRounding( array $row, array $transaction ): void {
		$rawNetAmount = (float)( $row['Net In Holding Currency'] ?? 0 );
		$roundedNetAmount = (float)( $transaction['settled_net_amount'] ?? 0 );

		$this->roundingAdjustment += $rawNetAmount - $roundedNetAmount;
		$this->roundingRows++;
	}

	/**
	 * Add the actual payout transaction from the matching Checkout.com payouts file.
	 *
	 * Settlement breakdown files only contain the merchant payout fee row. The
	 * authoritative payout amount is in a separate payouts_ent_...csv file.
	 *
	 * @param string $settlementFile
	 */
	protected function appendPayoutTransaction( string $settlementFile ): void {
		$payoutRow = $this->findPayoutRowForSettlementFile( $settlementFile );
		$this->appendRoundingAdjustmentIfNeeded( $payoutRow );
		$settlementRow = $this->convertPayoutRowToSettlementBreakdownRow( $payoutRow );

		$this->fileData[] = $this->getParser( $settlementRow )->getPayoutTransaction( $settlementRow );
	}

	/**
	 * Add a small synthetic fee row when Checkout.com's high-precision row-level
	 * settlement values differ from the authoritative payout amount after audit
	 * rows have been currency-rounded.
	 *
	 * @param array<string,string|null> $payoutRow
	 */
	protected function appendRoundingAdjustmentIfNeeded( array $payoutRow ): void {
		$currency = $payoutRow['Holding Currency'];
		$expectedPayoutAmount = Money::of( $payoutRow['Payout Amount'], $currency, null, RoundingMode::HalfUp );
		$actualSettlementAmount = Money::zero( $payoutRow['Holding Currency'] );
		$actualFeeAmount = Money::zero( $currency );
		$actualTotalAmount = Money::zero( $currency );

		foreach ( $this->fileData as $transaction ) {
			$actualSettlementAmount = $actualSettlementAmount->plus(
				Money::of( $transaction['settled_net_amount'], $payoutRow['Holding Currency'] )
			);
			$actualFeeAmount = $actualFeeAmount->plus(
				Money::of( $transaction['settled_fee_amount'], $payoutRow['Holding Currency'] )
			);
			$actualTotalAmount = $actualTotalAmount->plus(
				Money::of( $transaction['settled_total_amount'], $payoutRow['Holding Currency'] )
			);
			$check = $actualTotalAmount->plus( $actualFeeAmount )->minus( $actualSettlementAmount );
			if ( !$check->isEqualTo( Money::zero( $currency ) ) ) {
				throw new SmashPigException( 'Money addition issue total_amount ' . (string)$actualSettlementAmount->getAmount()
				  . 'should equal ' . (string)$actualFeeAmount->getAmount() . ' plus ' . (string)$actualTotalAmount->getAmount()
				);
			}
		}

		$adjustment = $expectedPayoutAmount->minus( $actualSettlementAmount );
		if ( $adjustment->isEqualTo( 0 ) ) {
			return;
		}

		$maximumExpectedAdjustment = Money::of( $this->roundingRows, $currency, null, RoundingMode::HalfUp )->multipliedBy( (string)self::MAX_ROUNDING_ADJUSTMENT_PER_ROW, RoundingMode::HalfUp );
		if ( $adjustment->abs()->isGreaterThan( $maximumExpectedAdjustment ) ) {
			throw new RuntimeException(
				"Checkout.com payout rounding adjustment {$adjustment} exceeds expected maximum "
				. "{$maximumExpectedAdjustment} for {$this->roundingRows} rows"
			);
		}

		Logger::info(
			'Checkout.com adding payout rounding adjustment for payout {payout_id}: adjustment {adjustment}, tracked_rounding {tracked_rounding}, rows {rows}',
			[
				'payout_id' => $payoutRow['Payout ID'],
				'adjustment' => (string)$adjustment->getAmount(),
				'tracked_rounding' => number_format( $this->roundingAdjustment, 8, '.', '' ),
				'rows' => $this->roundingRows,
			]
		);

		$this->fileData[] = [
			'gateway' => 'checkoutcom',
			'audit_file_gateway' => 'checkoutcom',
			'type' => 'fee',
			'gateway_txn_id' => 'rounding-' . strtoupper( $payoutRow['Payout ID'] ),
			'gateway_account' => '',
			'settlement_batch_reference' => strtoupper( $payoutRow['Payout ID'] ),
			'date' => $this->getUtcTimestamp( $payoutRow['Payout Date'] ),
			'settled_date' => $this->getUtcTimestamp( $payoutRow['Payout Date'] ),
			'settled_currency' => $payoutRow['Holding Currency'],
			'settled_total_amount' => '0.00',
			'settled_fee_amount' => (string)$adjustment->getAmount(),
			'settled_net_amount' => (string)$adjustment->getAmount(),
		];
	}

	protected function appendUnusedFeeRows(): void {
		foreach ( $this->feeRows as $rows ) {
			foreach ( $rows as $row ) {
				$this->parseRow( $row );
			}
		}
	}

	/**
	 * @param string $settlementFile
	 * @return array<string,string|null>
	 */
	protected function findPayoutRowForSettlementFile( string $settlementFile ): array {
		$parts = $this->extractSettlementFileParts( $settlementFile );

		foreach ( $this->getPayoutFileCandidates( $settlementFile ) as $payoutFile ) {
			foreach ( $this->readCsvRows( $payoutFile, $this->requiredPayoutColumns ) as $row ) {
				if ( strtoupper( $row['Payout ID'] ?? '' ) === $parts['payout_id'] ) {
					return $row;
				}
			}
		}

		throw new RuntimeException(
			"No Checkout.com payout row found for payout ID {$parts['payout_id']} from {$settlementFile}"
		);
	}

	/**
	 * @param string $settlementFile
	 * @return string[]
	 */
	protected function getPayoutFileCandidates( string $settlementFile ): array {
		$parts = $this->extractSettlementFileParts( $settlementFile );
		$settlementDate = DateTimeImmutable::createFromFormat( 'Ymd', $parts['date'] );

		if ( !$settlementDate ) {
			throw new RuntimeException( "Un-parseable Checkout.com settlement date: {$parts['date']}" );
		}

		$directories = [
			dirname( $settlementFile ),
			dirname( $settlementFile ) . '/../completed',
			dirname( $settlementFile ) . '/../ignored',
		];

		$candidates = [];
		foreach ( $directories as $directory ) {
			for ( $offset = -self::PAYOUT_SEARCH_DAYS; $offset <= self::PAYOUT_SEARCH_DAYS; $offset++ ) {
				$date = $settlementDate->modify( sprintf( '%+d days', $offset ) )->format( 'Ymd' );

				foreach ( [
					"{$directory}/payouts_{$parts['entity_id']}_*_{$date}_*.csv",
					"{$directory}/payouts_{$parts['entity_id']}_*_{$date}_*.csv.gz",
				] as $pattern ) {
					$candidates = array_merge( $candidates, glob( $pattern ) ?: [] );
				}
			}
		}

		return array_values( array_unique( $candidates ) );
	}

	/**
	 * @param string $file
	 * @return array{entity_id:string,date:string,payout_id:string}
	 */
	protected function extractSettlementFileParts( string $file ): array {
		if ( preg_match(
			'/^settlement-breakdown_(ent_[^_]+)_([0-9]{8})_([^_]+)_[0-9]+\.csv(?:\.gz)?$/',
			basename( $file ),
			$matches
		) ) {
			return [
				'entity_id' => $matches[1],
				'date' => $matches[2],
				'payout_id' => strtoupper( $matches[3] ),
			];
		}

		throw new RuntimeException( "Un-parseable Checkout.com settlement file name: {$file}" );
	}

	/**
	 * @param string $file
	 * @param string[] $requiredColumns
	 * @return array<int,array<string,string|null>>
	 */
	protected function readCsvRows( string $file, array $requiredColumns ): array {
		$isGzipped = str_ends_with( $file, '.gz' );
		$handle = $isGzipped ? gzopen( $file, 'rb' ) : fopen( $file, 'r' );

		if ( !$handle ) {
			throw new RuntimeException( "Unable to open payout file: {$file}" );
		}

		$headers = $this->getCsvRow( $handle, $isGzipped );
		if ( !$headers ) {
			$this->closeCsvHandle( $handle, $isGzipped );
			throw new RuntimeException( "Empty payout file: {$file}" );
		}

		$missingColumns = array_diff( $requiredColumns, $headers );
		if ( count( $missingColumns ) > 0 ) {
			$this->closeCsvHandle( $handle, $isGzipped );
			throw new RuntimeException( 'Missing payout columns ' . implode( ',', $missingColumns ) );
		}

		$rows = [];
		while ( $line = $this->getCsvRow( $handle, $isGzipped ) ) {
			$rows[] = array_combine( $headers, $line );
		}

		$this->closeCsvHandle( $handle, $isGzipped );

		return $rows;
	}

	protected function getCsvRow( $handle, bool $isGzipped ) {
		if ( !$isGzipped ) {
			return fgetcsv( $handle, 0, ',', '"', '\\' );
		}

		$line = gzgets( $handle );
		if ( $line === false ) {
			return false;
		}

		return str_getcsv( $line, ',', '"', '\\' );
	}

	/**
	 * @param resource $handle
	 * @param bool $isGzipped
	 */
	protected function closeCsvHandle( $handle, bool $isGzipped ): void {
		if ( $isGzipped ) {
			gzclose( $handle );
			return;
		}
		fclose( $handle );
	}

	/**
	 * Convert a payouts report row into the settlement-breakdown shape expected by
	 * SettlementBreakdownReport::getPayoutTransaction().
	 *
	 * @param array<string,string|null> $row
	 * @return array<string,string|null>
	 */
	protected function convertPayoutRowToSettlementBreakdownRow( array $row ): array {
		return [
			'Client Entity ID' => $row['Client Entity ID'],
			'Client Entity Name' => $row['Client Entity Name'],
			'Processing Channel ID' => '',
			'Processing Channel Name' => '',
			'Type' => 'Payout',
			'Payment ID' => '',
			'Reference' => '',
			'Processed On' => $row['Payout Date'],
			'Available On' => $row['Payout Date'],
			'Holding Currency' => $row['Holding Currency'],
			'Payout ID' => strtoupper( $row['Payout ID'] ),
			'Gross In Holding Currency' => $row['Payout Amount'],
			'Deduction In Holding Currency' => '0',
			'Net In Holding Currency' => $row['Payout Amount'],
			'Processing Fee In Holding Currency' => '0',
			'Scheme Fee In Holding Currency' => '0',
			'Interchange In Holding Currency' => '0',
			'Tax In Holding Currency' => '0',
			'Reserve In Holding Currency' => '0',
			'Processing Currency' => $row['Holding Currency'],
			'Gross In Processing Currency' => $row['Payout Amount'],
			'FX Rate Applied' => '1',
			'Payment Method' => '',
		];
	}

	protected function getUtcTimestamp( ?string $date ): ?int {
		if ( $date === null || $date === '' ) {
			return null;
		}
		return ( new \DateTimeImmutable( $date, new \DateTimeZone( 'UTC' ) ) )->getTimestamp();
	}

	private function getParser( array $row, array $feeRows = [] ): SettlementBreakdownReport {
		return new SettlementBreakdownReport( $row, $feeRows );
	}

}
