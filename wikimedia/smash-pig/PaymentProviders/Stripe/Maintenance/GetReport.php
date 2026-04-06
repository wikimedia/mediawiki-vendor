<?php

namespace SmashPig\PaymentProviders\Stripe\Maintenance;

use SmashPig\Core\Context;
use SmashPig\Core\Logging\Logger;
use SmashPig\Maintenance\MaintenanceBase;
use SmashPig\PaymentProviders\Stripe\Api;

require __DIR__ . '/../../../Maintenance/MaintenanceBase.php';

class GetReport extends MaintenanceBase {

	// Stripe payout reconciliation report for a single payout.
	// Docs: https://docs.stripe.com/reports/report-types/payout-reconciliation
	private const REPORT_BY_PAYOUT = 'payout_reconciliation.by_id.itemized.4';

	// Stripe activity report for balance changes over an interval.
	// Docs: https://docs.stripe.com/reports/report-types/balance#schema-balance-change-from-activity-itemized-7
	private const REPORT_PAYMENTS = 'balance_change_from_activity.itemized.7';

	// Stripe fees report over an interval.
	// Docs: https://docs.stripe.com/reports/report-types/all-fees
	private const REPORT_FEES = 'all_fees.balance_transaction_created.itemized.2';

	private const TYPE_SETTLEMENT_REPORT = 'settlement-report';
	private const TYPE_SETTLEMENT_API = 'settlement-api';
	private const TYPE_PAYMENTS = 'payments';
	private const TYPE_FEES = 'fees';

	private const VALID_REPORT_TYPES = [
		self::TYPE_SETTLEMENT_REPORT,
		self::TYPE_SETTLEMENT_API,
		self::TYPE_PAYMENTS,
		self::TYPE_FEES,
	];

	// Requested columns for the payout reconciliation report.
	// Docs: https://docs.stripe.com/reports/report-types/payout-reconciliation
	// Metadata column docs: https://docs.stripe.com/metadata
	// Keep this list conservative: Stripe rejects unsupported columns for a
	// specific report version with HTTP 400.
	private const SETTLEMENT_REPORT_COLUMNS = [
		'balance_transaction_id',
		'payment_metadata[external_identifier]',
		'payment_metadata[orchestrator_tx_ref]',
		'payment_metadata[orchestrator_tx_sid]',
		'payment_metadata[gr4vy_tx_ref]',
		'payment_metadata[gr4vy_tx_sid]',
		'source_id',
		'payment_intent_id',
		'charge_id',
		'created',
		'available_on',
		'automatic_payout_id',
		'automatic_payout_effective_at',
		'currency',
		'gross',
		'fee',
		'net',
		'reporting_category',
		'payment_method_type',
		'card_brand',
		'card_country',
		'card_funding',
		'description',
		'trace_id_status',
	];

	// Column order for settlement CSVs built directly from the Balance
	// Transactions API. Docs: https://docs.stripe.com/api/balance_transactions
	private const API_SETTLEMENT_COLUMNS = [
		'automatic_payout_effective_at',
		'automatic_payout_id',
		'available_on',
		'balance_transaction_id',
		'charge_id',
		'created',
		'currency',
		'description',
		'fee',
		'gross',
		'net',
		'payment_intent_id',
		'payment_metadata[external_identifier]',
		'payment_method_type',
		'card_brand',
		'card_country',
		'card_funding',
		'reporting_category',
		'source_id',
		'trace_id_status',
	];

	// Column order for payments activity exports.
	// Docs: https://docs.stripe.com/reports/report-types/balance-change-from-activity
	private const PAYMENTS_COLUMNS = [
		'automatic_payout_effective_at',
		'automatic_payout_id',
		'available_on',
		'balance_transaction_id',
		'charge_id',
		'created',
		'currency',
		'description',
		'fee',
		'gross',
		'net',
		'payment_intent_id',
		'payment_metadata[external_identifier]',
		'payment_method_type',
		'card_brand',
		'card_country',
		'card_funding',
		'reporting_category',
		'source_id',
		'trace_id_status',
	];

	// Column order for fee exports.
	// Docs: https://docs.stripe.com/reports/report-types/all-fees
	private const FEES_COLUMNS = [
		'balance_transaction_created',
		'balance_transaction_id',
		'fee_transaction_created',
		'fee_transaction_id',
		'incurred_at',
		'incurred_by',
		'incurred_by_type',
		'fee_description',
		'amount',
		'tax',
		'currency',
		'product',
		'feature_name',
		'pricing_tier',
		'settled_at',
		'settled_via',
		'suite',
		'fee_category',
	];

	private array $sourceCache = [];

	private \SmashPig\Core\ProviderConfiguration $config;

	public function __construct() {
		parent::__construct();
		$this->addOption( 'payout-id', 'Stripe payout id for a single settlement file', '', 'p' );
		$this->addFlag( 'list-payouts', 'List payouts in the requested date range and download one settlement file per payout', 'l' );
		$this->addOption( 'status', 'Optional Stripe payout status filter when using --list-payouts', null );
		$this->addOption( 'start-date', 'Arrival date lower bound (YYYY-MM-DD) when using --list-payouts or interval reports', null, 's' );
		$this->addOption( 'end-date', 'Arrival date upper bound (YYYY-MM-DD) when using --list-payouts or interval reports', null, 'e' );
		$this->addOption( 'timezone', 'IANA timezone for report output', null, 't' );
		$this->addOption( 'path', 'Directory to store the downloaded report', null, 'o' );
		$this->addOption( 'gateway-account', 'Gateway account name. Used for API key lookup (STRIPE_SECRET_KEY_<account>), filenames, and CSV output.', '', 'g' );
		$this->addOption( 'report-type', 'Which report(s) to generate: settlement-report, settlement-api, payments, fees. Comma-separated allowed.', null, 'r' );
		$this->addOption( 'add-payout-row', 'Append a synthetic payout row to each settlement CSV (default 1)', null );
		$this->addFlag( 'write-empty-files', 'Write header-only CSV files when no rows are retrieved; otherwise only log and skip writing', 'n' );
		$this->addOption( 'poll-interval', 'Seconds between report status checks', 10, 'i' );
		$this->addOption( 'poll-timeout', 'Maximum seconds to wait for Stripe to finish the report', 1800, 'w' );
		$this->addFlag( 'compress-file', 'Ask Stripe to ZIP the report file', 'z' );
		$this->desiredOptions['config-node']['default'] = 'stripe';
	}

	public function execute(): void {
		$this->config = Context::get()->getProviderConfiguration();
		$path = rtrim( $this->getOutputPath(), '/' );
		if ( !is_dir( $path ) ) {
			throw new \RuntimeException( 'Output directory does not exist: ' . $path );
		}

		$api = new Api( [ 'gateway_account' => $this->getGatewayAccount() ] );
		$reportTypes = $this->getRequestedReportTypes();

		if ( $this->needsPayouts( $reportTypes ) ) {
			$this->executePayoutScopedReports( $api, $path, $reportTypes );
		}

		if ( $this->needsIntervalReports( $reportTypes ) ) {
			$this->executeIntervalReports( $api, $path, $reportTypes );
		}
	}

	private function executePayoutScopedReports( Api $api, string $path, array $reportTypes ): void {
		$payouts = [];
		if ( $this->shouldListPayouts() ) {
			$payouts = $this->fetchPayouts( $api );
		} else {
			$payoutId = (string)$this->getOption( 'payout-id' );
			if ( $payoutId === '' ) {
				throw new \InvalidArgumentException(
					'Provide --payout-id or use --list-payouts when requesting settlement-report or settlement-api.'
				);
			}
			$payouts[] = $api->getPayout( $payoutId );
		}

		foreach ( $payouts as $payout ) {
			foreach ( $reportTypes as $reportType ) {
				if ( $reportType === self::TYPE_SETTLEMENT_REPORT ) {
					$this->downloadReportSettlementForPayout( $api, $path, $payout );
				} elseif ( $reportType === self::TYPE_SETTLEMENT_API ) {
					$this->downloadApiSettlementForPayout( $api, $path, $payout );
				}
			}
		}

		Logger::info( 'Processed ' . count( $payouts ) . ' Stripe payouts' );
	}

	private function executeIntervalReports( Api $api, string $path, array $reportTypes ): void {
		$startDate = $this->getEffectiveStartDate();
		$endDate = $this->getEffectiveEndDate();

		foreach ( $reportTypes as $reportType ) {
			if ( $reportType === self::TYPE_PAYMENTS ) {
				$this->downloadIntervalReport(
					$api,
					$path,
					self::REPORT_PAYMENTS,
					self::PAYMENTS_COLUMNS,
					$this->buildIntervalFilename( 'payments-activity', $startDate, $endDate )
				);
			} elseif ( $reportType === self::TYPE_FEES ) {
				$this->downloadIntervalReport(
					$api,
					$path,
					self::REPORT_FEES,
					self::FEES_COLUMNS,
					$this->buildIntervalFilename( 'fees', $startDate, $endDate )
				);
			}
		}
	}

	private function getRequestedReportTypes(): array {
		$value = trim( (string)$this->chooseOptionOrConfig( 'report-type', [ 'default_report_type' ], self::TYPE_SETTLEMENT_REPORT ) );
		$requested = array_values( array_filter( array_map( 'trim', explode( ',', $value ) ) ) );
		if ( !$requested ) {
			return [ self::TYPE_SETTLEMENT_REPORT ];
		}

		$invalid = array_diff( $requested, self::VALID_REPORT_TYPES );
		if ( $invalid ) {
			throw new \InvalidArgumentException(
				'Invalid --report-type value(s): ' . implode( ', ', $invalid ) .
				'. Valid values: ' . implode( ', ', self::VALID_REPORT_TYPES )
			);
		}

		return array_values( array_unique( $requested ) );
	}

	private function needsPayouts( array $reportTypes ): bool {
		return (bool)array_intersect( $reportTypes, [ self::TYPE_SETTLEMENT_REPORT, self::TYPE_SETTLEMENT_API ] );
	}

	private function needsIntervalReports( array $reportTypes ): bool {
		return (bool)array_intersect( $reportTypes, [ self::TYPE_PAYMENTS, self::TYPE_FEES ] );
	}

	private function fetchPayouts( Api $api ): array {
		$startDate = $this->getEffectiveStartDate();
		$endDate = $this->getEffectiveEndDate();

		$filters = [
			'limit' => 100,
			'status' => $this->getEffectiveStatus(),
			'arrival_date' => [
				'gte' => strtotime( $startDate . ' 00:00:00 UTC' ),
				'lte' => strtotime( $endDate . ' 23:59:59 UTC' ),
			],
		];

		$allPayouts = [];
		do {
			$result = $api->listPayouts( $filters );
			foreach ( $result['data'] ?? [] as $payout ) {
				if ( ( $payout['reconciliation_status'] ?? null ) !== 'completed' ) {
					Logger::info( 'Skipping payout ' . ( $payout['id'] ?? '(unknown)' ) . ' because reconciliation_status is not completed' );
					continue;
				}
				$allPayouts[] = $payout;
			}

			if ( !empty( $result['has_more'] ) && !empty( $result['data'] ) ) {
				$lastPayout = end( $result['data'] );
				$filters['starting_after'] = $lastPayout['id'];
			} else {
				unset( $filters['starting_after'] );
				break;
			}
		} while ( true );

		return $allPayouts;
	}

	/**
	 * Generate a payout-scoped settlement CSV via Stripe's Reports API.
	 *
	 * Docs: https://docs.stripe.com/reports/api
	 * Report schema docs: https://docs.stripe.com/reports/report-types/payout-reconciliation
	 *
	 * @param \SmashPig\PaymentProviders\Stripe\Api $api
	 * @param string $path
	 * @param array $payout
	 *
	 * @return void
	 */
	private function downloadReportSettlementForPayout( Api $api, string $path, array $payout ): void {
		$payoutId = $this->requirePayoutId( $payout );
		Logger::info( 'Creating Stripe report-run settlement for payout ' . $payoutId );
		$reportRun = $api->createReportRun( self::REPORT_BY_PAYOUT, $this->buildReportRunParameters( $payoutId, self::SETTLEMENT_REPORT_COLUMNS ) );
		$completedRun = $this->waitForCompletion( $api, $reportRun['id'] );
		$downloadUrl = $completedRun['result']['url'] ?? $completedRun['result']['file']['download_url']['url'] ?? null;
		if ( !$downloadUrl ) {
			throw new \RuntimeException( 'Stripe returned no download URL for the finished report' );
		}
		$fileContents = $api->downloadFile( $downloadUrl );
		if ( $this->shouldAddPayoutRow() ) {
			$fileContents = $this->appendPayoutRow( $fileContents, $payout );
		}
		$filename = $this->buildPayoutFilename( self::TYPE_SETTLEMENT_REPORT, $payout );
		if ( !$this->csvHasDataRows( $fileContents ) && !$this->shouldWriteEmptyFiles() ) {
			Logger::info( 'No Stripe rows retrieved for ' . $filename . '; not writing file' );
			return;
		}
		$this->writeFile( $path, $filename, $fileContents );
	}

	/**
	 * Generate a payout-scoped settlement CSV directly from the Balance.
	 *
	 * Transactions API instead of a Stripe report run.
	 * Docs: https://docs.stripe.com/api/balance_transactions
	 *
	 * @param \SmashPig\PaymentProviders\Stripe\Api $api
	 * @param string $path
	 * @param array $payout
	 *
	 * @return void
	 */
	private function downloadApiSettlementForPayout( Api $api, string $path, array $payout ): void {
		$payoutId = $this->requirePayoutId( $payout );
		Logger::info( 'Creating Stripe API settlement CSV for payout ' . $payoutId );
		$rows = [];
		$startingAfter = null;
		do {
			$result = $api->listBalanceTransactionsForPayout( $payoutId, $startingAfter );
			foreach ( $result['data'] ?? [] as $transaction ) {
				$rows[] = $this->normalizeBalanceTransactionRow( $api, $payout, $transaction );
			}
			if ( !empty( $result['has_more'] ) && !empty( $result['data'] ) ) {
				$last = end( $result['data'] );
				$startingAfter = $last['id'];
			} else {
				$startingAfter = null;
			}
		} while ( $startingAfter );

		if ( $this->shouldAddPayoutRow() ) {
			$rows[] = $this->buildSyntheticPayoutRow( $payout, self::API_SETTLEMENT_COLUMNS );
		}

		$filename = $this->buildPayoutFilename( self::TYPE_SETTLEMENT_API, $payout );
		if ( !$this->hasDataRows( $rows ) && !$this->shouldWriteEmptyFiles() ) {
			Logger::info( 'No Stripe rows retrieved for ' . $filename . '; not writing file' );
			return;
		}

		$this->writeFile(
			$path,
			$filename,
			$this->rowsToCsv( self::API_SETTLEMENT_COLUMNS, $rows )
		);
	}

	private function downloadIntervalReport(
		Api $api,
		string $path,
		string $reportType,
		array $columns,
		string $filename
	): void {
		$params = [
			'interval_start' => strtotime( $this->getEffectiveStartDate() . ' 00:00:00 UTC' ),
			'interval_end' => strtotime( $this->getEffectiveEndDate() . ' 23:59:59 UTC' ),
			'timezone' => $this->getEffectiveTimezone(),
		];
		foreach ( $columns as $i => $column ) {
			$params["columns[$i]"] = $column;
		}
		$reportRun = $api->createReportRun( $reportType, $params );
		$completedRun = $this->waitForCompletion( $api, $reportRun['id'] );
		$downloadUrl = $completedRun['result']['url'] ?? $completedRun['result']['file']['download_url']['url'] ?? null;
		if ( !$downloadUrl ) {
			throw new \RuntimeException( 'Stripe returned no download URL for the finished report' );
		}
		$contents = $api->downloadFile( $downloadUrl );
		if ( $this->getGatewayAccount() !== '' ) {
			$contents = $this->appendGatewayAccountColumn( $contents );
		}
		if ( !$this->csvHasDataRows( $contents ) && !$this->shouldWriteEmptyFiles() ) {
			Logger::info( 'No Stripe rows retrieved for ' . $filename . '; not writing file' );
			return;
		}
		$this->writeFile( $path, $filename, $contents );
	}

	private function buildReportRunParameters( string $payoutId, array $columns ): array {
		$params = array_filter( [
			'timezone' => $this->getEffectiveTimezone(),
			'compress_file' => $this->getOption( 'compress-file' ) ? 'true' : null,
			'payout' => $payoutId,
		], static fn ( $value ) => $value !== null );

		foreach ( $columns as $i => $column ) {
			$params["columns[$i]"] = $column;
		}
		return $params;
	}

	private function normalizeBalanceTransactionRow( Api $api, array $payout, array $transaction ): array {
		$sourceId = is_string( $transaction['source'] ?? null ) ? $transaction['source'] : ( $transaction['source']['id'] ?? '' );
		$sourceData = $sourceId !== '' ? $this->getSourceData( $api, $sourceId ) : [];
		$paymentIntent = is_array( $sourceData['payment_intent'] ?? null ) ? $sourceData['payment_intent'] : [];
		$paymentMethodType = (string)( $sourceData['payment_method_details']['type'] ?? '' );
		if ( $paymentMethodType === '' && isset( $sourceData['payment_method_details']['card'] ) ) {
			$paymentMethodType = 'card';
		}

		return [
			'automatic_payout_effective_at' => $this->formatUtcTimestamp( $payout['arrival_date'] ?? null ),
			'automatic_payout_id' => $payout['id'] ?? '',
			'available_on' => $this->formatUtcTimestamp( $transaction['available_on'] ?? null ),
			'balance_transaction_id' => $transaction['id'] ?? '',
			'charge_id' => $this->deriveChargeId( $sourceId, $sourceData ),
			'created' => $this->formatUtcTimestamp( $transaction['created'] ?? null ),
			'currency' => strtoupper( (string)( $transaction['currency'] ?? $payout['currency'] ?? '' ) ),
			'description' => (string)( $transaction['description'] ?? '' ),
			'fee' => $this->formatAmount( (int)( $transaction['fee'] ?? 0 ), (string)( $transaction['currency'] ?? $payout['currency'] ?? '' ) ),
			'gross' => $this->formatAmount( (int)( $transaction['amount'] ?? 0 ), (string)( $transaction['currency'] ?? $payout['currency'] ?? '' ) ),
			'net' => $this->formatAmount( (int)( $transaction['net'] ?? 0 ), (string)( $transaction['currency'] ?? $payout['currency'] ?? '' ) ),
			'payment_intent_id' => (string)( $paymentIntent['id'] ?? '' ),
			'payment_metadata[external_identifier]' => (string)( $paymentIntent['metadata']['external_identifier'] ?? '' ),
			'payment_method_type' => $paymentMethodType,
			'card_brand' => (string)( $sourceData['payment_method_details']['card']['brand'] ?? '' ),
			'card_country' => (string)( $sourceData['payment_method_details']['card']['country'] ?? '' ),
			'card_funding' => (string)( $sourceData['payment_method_details']['card']['funding'] ?? '' ),
			'reporting_category' => (string)( $transaction['reporting_category'] ?? $transaction['type'] ?? '' ),
			'source_id' => $sourceId,
			'trace_id_status' => (string)( $payout['trace_id_status'] ?? '' ),
			'gateway_account' => $this->getGatewayAccount(),
		];
	}

	private function getSourceData( Api $api, string $sourceId ): array {
		if ( isset( $this->sourceCache[$sourceId] ) ) {
			return $this->sourceCache[$sourceId];
		}

		if ( str_starts_with( $sourceId, 'ch_' ) ) {
			$result = $api->getCharge( $sourceId );
			$this->sourceCache[$sourceId] = $result;
			return $result;
		}

		if ( str_starts_with( $sourceId, 're_' ) ) {
			$refund = $api->getRefund( $sourceId );
			$charge = is_array( $refund['charge'] ?? null ) ? $refund['charge'] : [];

			$refund['payment_intent'] = is_array( $charge['payment_intent'] ?? null )
				? $charge['payment_intent']
				: [];

			$refund['payment_method_details'] = $charge['payment_method_details'] ?? [];

			$this->sourceCache[$sourceId] = $refund;
			return $refund;
		}

		if ( str_starts_with( $sourceId, 'dp_' ) ) {
			$dispute = $api->getDispute( $sourceId );
			$charge = is_array( $dispute['charge'] ?? null ) ? $dispute['charge'] : [];

			$dispute['payment_intent'] = is_array( $charge['payment_intent'] ?? null )
				? $charge['payment_intent']
				: [];

			$dispute['payment_method_details'] = $charge['payment_method_details'] ?? [];

			$this->sourceCache[$sourceId] = $dispute;
			return $dispute;
		}

		$this->sourceCache[$sourceId] = [];
		return [];
	}

	private function deriveChargeId( string $sourceId, array $sourceData ): string {
		if ( str_starts_with( $sourceId, 'ch_' ) ) {
			return $sourceId;
		}
		if ( is_string( $sourceData['charge'] ?? null ) ) {
			return $sourceData['charge'];
		}
		if ( is_array( $sourceData['charge'] ?? null ) ) {
			return (string)( $sourceData['charge']['id'] ?? '' );
		}
		return '';
	}

	private function getGatewayAccount(): string {
		$gatewayAccount = trim( (string)$this->chooseOptionOrConfig( 'gateway-account', [ 'gateway_account' ], '' ) );
		if ( $gatewayAccount === '' ) {
			throw new \InvalidArgumentException( '--gateway-account is required.' );
		}
		return $gatewayAccount;
	}

	private function shouldWriteEmptyFiles(): bool {
		return $this->asBool( $this->chooseOptionOrConfig( 'write-empty-files', [ 'write_empty_files' ], false ) );
	}

	private function hasDataRows( array $rows ): bool {
		return $rows !== [];
	}

	private function csvHasDataRows( string $csvContents ): bool {
		$trimmed = rtrim( $csvContents, "
" );
		if ( $trimmed === '' ) {
			return false;
		}

		$lines = preg_split( '/
|
|
/', $trimmed );
		if ( !$lines || count( $lines ) <= 1 ) {
			return false;
		}

		foreach ( array_slice( $lines, 1 ) as $line ) {
			if ( trim( $line ) !== '' ) {
				return true;
			}
		}

		return false;
	}

	private function shouldAddPayoutRow(): bool {
		$value = (string)$this->chooseOptionOrConfig( 'add-payout-row', [ 'add_payout_row' ], true );
		return !in_array( strtolower( $value ), [ '0', 'false', 'no', 'off' ], true );
	}

	/**
	 * Add a synthetic payout row to a settlement CSV. Stripe's payout
	 * reconciliation export focuses on the balance transactions in the payout,
	 * so we add an explicit payout row for easier downstream reconciliation.
	 * Payout object docs: https://docs.stripe.com/api/payouts/object
	 */
	private function appendPayoutRow( string $csvContents, array $payout ): string {
		$trimmed = rtrim( $csvContents, "\r\n" );
		$lines = preg_split( '/\r\n|\n|\r/', $trimmed );
		if ( !$lines || !isset( $lines[0] ) ) {
			return $csvContents;
		}
		$headers = str_getcsv( $lines[0] );
		$row = $this->buildSyntheticPayoutRow( $payout, $headers );
		$handle = fopen( 'php://temp', 'r+' );
		fputcsv( $handle, array_map( static fn ( string $header ) => $row[$header] ?? '', $headers ) );
		rewind( $handle );
		$payoutCsvRow = stream_get_contents( $handle );
		fclose( $handle );
		return $trimmed . PHP_EOL . rtrim( (string)$payoutCsvRow, "\r\n" ) . PHP_EOL;
	}

	private function buildSyntheticPayoutRow( array $payout, array $headers ): array {
		$row = array_fill_keys( $headers, '' );
		$amount = $this->formatAmount( (int)( $payout['amount'] ?? 0 ), (string)( $payout['currency'] ?? '' ) );
		$payoutDate = $this->formatUtcTimestamp( $payout['arrival_date'] ?? null );
		$createdDate = $this->formatUtcTimestamp( $payout['created'] ?? null );

		$this->setIfPresent( $row, 'automatic_payout_effective_at_utc', $payoutDate );
		$this->setIfPresent( $row, 'automatic_payout_effective_at', $payoutDate );
		$this->setIfPresent( $row, 'automatic_payout_id', (string)( $payout['id'] ?? '' ) );
		$this->setIfPresent( $row, 'available_on_utc', $payoutDate );
		$this->setIfPresent( $row, 'available_on', $payoutDate );
		$this->setIfPresent( $row, 'balance_transaction_id', (string)( $payout['balance_transaction'] ?? '' ) );
		$this->setIfPresent( $row, 'created_utc', $createdDate );
		$this->setIfPresent( $row, 'created', $createdDate );
		$this->setIfPresent( $row, 'currency', strtoupper( (string)( $payout['currency'] ?? '' ) ) );
		$this->setIfPresent( $row, 'description', 'Payout' );
		$this->setIfPresent( $row, 'fee', '0.00' );
		$this->setIfPresent( $row, 'gross', $amount );
		$this->setIfPresent( $row, 'net', $amount );
		$this->setIfPresent( $row, 'reporting_category', 'payout' );
		$this->setIfPresent( $row, 'source_id', (string)( $payout['id'] ?? '' ) );
		$this->setIfPresent( $row, 'trace_id_status', (string)( $payout['trace_id_status'] ?? '' ) );
		$this->setIfPresent( $row, 'gateway_account', $this->getGatewayAccount() );

		return $row;
	}

	private function setIfPresent( array &$row, string $header, string $value ): void {
		if ( array_key_exists( $header, $row ) ) {
			$row[$header] = $value;
		}
	}

	private function rowsToCsv( array $headers, array $rows ): string {
		$handle = fopen( 'php://temp', 'r+' );
		fputcsv( $handle, $headers );
		foreach ( $rows as $row ) {
			fputcsv( $handle, array_map( static fn ( string $header ) => $row[$header] ?? '', $headers ) );
		}
		rewind( $handle );
		$contents = (string)stream_get_contents( $handle );
		fclose( $handle );
		return $contents;
	}

	private function appendGatewayAccountColumn( string $csvContents ): string {
		$gatewayAccount = $this->getGatewayAccount();
		if ( $gatewayAccount === '' ) {
			return $csvContents;
		}

		$trimmed = rtrim( $csvContents, "\r\n" );
		$lines = preg_split( '/\r\n|\n|\r/', $trimmed );
		if ( !$lines || !isset( $lines[0] ) ) {
			return $csvContents;
		}

		$handle = fopen( 'php://temp', 'r+' );
		if ( !$handle ) {
			throw new \RuntimeException( 'Unable to open temporary stream for gateway account column.' );
		}

		foreach ( $lines as $index => $line ) {
			$row = str_getcsv( $line );
			$row[] = $index === 0 ? 'gateway_account' : $gatewayAccount;
			fputcsv( $handle, $row );
		}

		rewind( $handle );
		$contents = (string)stream_get_contents( $handle );
		fclose( $handle );
		return $contents;
	}

	private function getOutputPath(): string {
		$path = $this->chooseOptionOrConfig( 'path', [ 'reports_incoming_path' ], '' );
		if ( !is_string( $path ) || trim( $path ) === '' ) {
			throw new \InvalidArgumentException( 'path is required (or set reports_incoming_path in config).' );
		}
		return trim( $path );
	}

	private function getEffectiveStartDate(): string {
		$startDate = trim( (string)$this->chooseOptionOrConfig( 'start-date', [ 'default_report_start_date' ], '' ) );
		return $startDate !== '' ? $startDate : gmdate( 'Y-m-d', strtotime( 'yesterday UTC' ) );
	}

	private function getEffectiveEndDate(): string {
		$endDate = trim( (string)$this->chooseOptionOrConfig( 'end-date', [ 'default_report_end_date' ], '' ) );
		return $endDate !== '' ? $endDate : $this->getEffectiveStartDate();
	}

	private function getEffectiveTimezone(): string {
		$timezone = trim( (string)$this->chooseOptionOrConfig( 'timezone', [ 'timezone' ], 'UTC' ) );
		return $timezone !== '' ? $timezone : 'UTC';
	}

	private function getEffectiveStatus(): string {
		$status = trim( (string)$this->getOption( 'status', 'paid' ) );
		return $status !== '' ? $status : 'paid';
	}

	private function shouldListPayouts(): bool {
		$payoutId = trim( (string)$this->getOption( 'payout-id' ) );
		if ( $payoutId !== '' ) {
			return false;
		}
		return $this->asBool( $this->getOption( 'list-payouts', true ) );
	}

	private function getFromConfig( string $path, mixed $default = null ): mixed {
		return $this->config->get( $path ) ?: $default;
	}

	private function chooseOptionOrConfig( string $optName, array $configPaths, mixed $default = null ): mixed {
		$opt = $this->getOption( $optName );
		if ( $opt !== null && $opt !== '' && $opt !== false ) {
			return $opt;
		}
		foreach ( $configPaths as $path ) {
			$value = $this->getFromConfig( $path, null );
			if ( $value !== null && $value !== '' ) {
				return $value;
			}
		}
		return $default;
	}

	private function asBool( mixed $value ): bool {
		if ( is_bool( $value ) ) {
			return $value;
		}
		if ( $value === null ) {
			return false;
		}
		return in_array( strtolower( trim( (string)$value ) ), [ '1', 'true', 'yes', 'y', 'on' ], true );
	}

	private function formatAmount( int $amount, string $currency ): string {
		$zeroDecimalCurrencies = [ 'bif', 'clp', 'djf', 'gnf', 'jpy', 'kmf', 'krw', 'mga', 'pyg', 'rwf', 'ugx', 'vnd', 'vuv', 'xaf', 'xof', 'xpf' ];
		$currency = strtolower( $currency );
		$divisor = in_array( $currency, $zeroDecimalCurrencies, true ) ? 1 : 100;
		return number_format( $amount / $divisor, 2, '.', '' );
	}

	private function formatUtcTimestamp( int $timestamp ): string {
		if ( !$timestamp ) {
			return '';
		}
		return gmdate( 'Y-m-d H:i:s', (int)$timestamp );
	}

	private function waitForCompletion( Api $api, string $reportRunId ): array {
		Logger::info( 'Waiting for completion of ' . $reportRunId );
		$pollInterval = max( 1, (int)$this->chooseOptionOrConfig( 'poll-interval', [ 'poll_interval' ], 5 ) );
		$timeout = max( $pollInterval, (int)$this->chooseOptionOrConfig( 'poll-timeout', [ 'poll_timeout' ], 300 ) );
		$deadline = time() + $timeout;
		do {
			$reportRun = $api->getReportRun( $reportRunId );
			$status = $reportRun['status'] ?? 'unknown';
			if ( $status === 'succeeded' ) {
				return $reportRun;
			}
			if ( $status === 'failed' ) {
				$error = $reportRun['error'] ?? 'unknown error';
				throw new \RuntimeException( 'Stripe report run failed: ' . ( is_string( $error ) ? $error : json_encode( $error ) ) );
			}
			if ( time() >= $deadline ) {
				throw new \RuntimeException(
					'Timed out waiting for Stripe report ' . $reportRunId .
					' last_status=' . $status
				);
			}
			sleep( $pollInterval );
		} while ( true );
	}

	private function requirePayoutId( array $payout ): string {
		$payoutId = $payout['id'] ?? null;
		if ( !$payoutId ) {
			throw new \RuntimeException( 'Missing payout id in Stripe API response.' );
		}
		return $payoutId;
	}

	private function buildPayoutFilename( string $prefix, array $payout ): string {
		$effectiveDate = $this->extractDate( $payout['arrival_date'] ?? null ) ?? $this->extractDate( $payout['created'] ?? null ) ?? gmdate( 'Y-m-d' );
		$payoutId = preg_replace( '/[^A-Za-z0-9_-]+/', '-', (string)( $payout['id'] ?? 'unknown' ) );
		$gatewayAccount = $this->sanitizeForFilename( $this->getGatewayAccount() );
		if ( $gatewayAccount !== '' ) {
			return sprintf( '%s-%s-%s-%s.csv', $prefix, $effectiveDate, $gatewayAccount, $payoutId );
		}
		return sprintf( '%s-%s-%s.csv', $prefix, $effectiveDate, $payoutId );
	}

	private function buildIntervalFilename( string $prefix, string $startDate, string $endDate ): string {
		$gatewayAccount = $this->sanitizeForFilename( $this->getGatewayAccount() );
		if ( $gatewayAccount !== '' ) {
			return sprintf( '%s-%s-to-%s-%s.csv', $prefix, $startDate, $endDate, $gatewayAccount );
		}
		return sprintf( '%s-%s-to-%s.csv', $prefix, $startDate, $endDate );
	}

	private function sanitizeForFilename( string $value ): string {
		return preg_replace( '/[^A-Za-z0-9_-]+/', '-', $value );
	}

	private function extractDate( int $timestamp ): ?string {
		if ( !$timestamp ) {
			return null;
		}
		return gmdate( 'Y-m-d', (int)$timestamp );
	}

	private function writeFile( string $path, string $filename, string $contents ): void {
		file_put_contents( $path . '/' . $filename, $contents );
		Logger::info( 'Saved Stripe file to ' . $path . '/' . $filename );
	}
}

$maintClass = GetReport::class;
require RUN_MAINTENANCE_IF_MAIN;
