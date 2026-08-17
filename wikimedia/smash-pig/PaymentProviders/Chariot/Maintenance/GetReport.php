<?php

namespace SmashPig\PaymentProviders\Chariot\Maintenance;

use SmashPig\Core\Context;
use SmashPig\Core\Helpers\CurrencyRoundingHelper;
use SmashPig\Core\Logging\Logger;
use SmashPig\Core\ProviderConfiguration;
use SmashPig\Maintenance\MaintenanceBase;
use SmashPig\PaymentProviders\Chariot\Api;
use SmashPig\PaymentProviders\Chariot\ChariotObjectMetadata;
use SmashPig\PaymentProviders\Chariot\Deposit;
use SmashPig\PaymentProviders\Chariot\Donation;
use SmashPig\PaymentProviders\Chariot\PendingDepositTracker;
use SmashPig\PaymentProviders\Chariot\UnknownPathCollector;

require __DIR__ . '/../../../Maintenance/MaintenanceBase.php';

class GetReport extends MaintenanceBase {

	private const MODE_DEPOSITS = 'deposits';
	private const MODE_DEPOSIT = 'deposit';
	private array $allUnknownDepositPaths = [];
	private array $allUnknownDonationPaths = [];

	private const VALID_MODES = [
		self::MODE_DEPOSITS,
		self::MODE_DEPOSIT,
	];

	private const ROUNDING_FEE_NOTE = 'FX rounding adjustment';

	/**
	 * These columns appear in the final csv.
	 */
	private const AUDIT_CSV_COLUMNS = [
		'gateway',
		'audit_file_gateway',
		'backend_processor',
		'gateway_txn_id',
		'backend_processor_txn_id',
		'banking_institution',
		'is_matching_gift',
		'is_daf',
		'is_endowment',
		'donor_advised_fund_name',
		'matching_gift_organization',
		'original_total_amount',
		'original_fee_amount',
		'original_net_amount',
		'original_matching_gift_total_amount',
		'original_matching_gift_fee_amount',
		'original_matching_gift_net_amount',
		'original_individual_gift_total_amount',
		'original_individual_gift_fee_amount',
		'original_individual_gift_net_amount',
		'settled_matching_gift_total_amount',
		'settled_matching_gift_fee_amount',
		'settled_matching_gift_net_amount',
		'settled_individual_gift_total_amount',
		'settled_individual_gift_fee_amount',
		'settled_individual_gift_net_amount',
		'settlement_batch_reference',
		'settled_total_amount',
		'settled_fee_amount',
		'settled_net_amount',
		'settled_currency',
		'settled_date',
		'exchange_rate',
		'original_currency',
		'date',
		'type',
		'gift_source',
		'direct_mail_appeal',
		'first_name',
		'last_name',
		'full_name',
		'partner_full_name',
		'prefix',
		'suffix',
		'email',
		'phone',
		'country',
		'postal_code',
		'state_province',
		'city',
		'street_address',
		'supplemental_address_1',
		'payment_method',
		'note',
		'dafpay_frequency',
		'dafpay_tracking_id',
		'dafpay_type',
		'dafpay_url',
		'check_number',
	];

	private ProviderConfiguration $config;
	private PendingDepositTracker $pendingDepositTracker;
	private Api $api;

	/**
	 * @throws \SmashPig\Core\SmashPigException
	 */
	public function __construct() {
		parent::__construct();
		$this->addOption( 'mode', 'Which Chariot API call to run', self::MODE_DEPOSITS, 'r' );
		$this->addOption(
			'deposit-id',
			'Optional deposit id; when provided, fetch only that deposit and ignore --start-date/--end-date',
			'',
			'd'
		);
		$this->addOption( 'start-date', 'Filter deposits by settled_at.after; accepts any strtotime()-parseable date/time', 'yesterday' );
		$this->addOption( 'end-date', 'Filter deposits by settled_at.before; accepts any strtotime()-parseable date/time', '' );
		$this->addOption( 'limit', 'Optional maximum results per deposits/donations list call', '', 'l' );
		$this->addOption( 'max-pages', 'Optional maximum pages to fetch for list calls', '', 'm' );
		$this->addFlag( 'stdout', 'Print summary JSON payload to stdout for list mode', 's' );
		$this->addFlag( 'include-json', 'Always write per-deposit JSON payloads even when there are no unknowns', '' );
		$this->desiredOptions['config-node']['default'] = 'chariot';
	}

	public function execute(): void {
		$this->config = Context::get()->getProviderConfiguration();
		$path = $this->getIncomingPath();
		if ( !is_dir( $path ) ) {
			throw new \RuntimeException( 'Output directory does not exist: ' . $path );
		}
		$this->pendingDepositTracker = new PendingDepositTracker( $path );
		$this->api = new Api();

		foreach ( $this->getRequestedModes() as $mode ) {
			switch ( $mode ) {
				case self::MODE_DEPOSITS:
					$this->runDeposits( $path );
					break;
				case self::MODE_DEPOSIT:
					$this->runDeposit( $path );
					break;
			}
		}
		$this->logUnknownPathsSummary();
	}

	private function runDeposits( string $path ): void {
		$depositId = trim( (string)$this->getOption( 'deposit-id' ) );
		if ( $depositId !== '' ) {
			$depositObject = $this->fetchDeposit( $depositId );
			$deposit = $depositObject->getDeposit();
			$this->writeDepositArtifacts( $path, $depositObject, $deposit );

			if ( $this->getOption( 'stdout' ) ) {
				$summary = [
					'mode' => self::MODE_DEPOSITS,
					'count' => 1,
					'next_tokens' => [],
					'deposit_ids' => [ $depositObject->getId() ],
				];
				$json = json_encode( $summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES );
				if ( $json !== false ) {
					print $json . PHP_EOL;
				}
			}

			return;
		}

		$result = $this->collectPagedResults(
			fn ( ?string $token ): array => $this->fetchDepositsPage( $token ),
			'next_page_token',
			'nextPageToken'
		);

		$attemptedIds = $writtenIds = [];
		foreach ( $result['results'] as $deposit ) {
			if ( !is_array( $deposit ) ) {
				continue;
			}
			$depositObject = new Deposit( $deposit );
			$attemptedIds[] = $depositObject->getId();
			if ( $this->writeDepositArtifacts( $path, $depositObject, $deposit ) ) {
				$writtenIds[] = $depositObject->getId();
			}
		}

		$this->retryPendingDeposits( $path, $attemptedIds );

		if ( $this->getOption( 'stdout' ) ) {
			$summary = [
				'mode' => self::MODE_DEPOSITS,
				'count' => count( $writtenIds ),
				'attempted' => count( $attemptedIds ),
				'next_tokens' => $result['next_tokens'],
				'deposit_ids' => $attemptedIds,
			];
			$json = json_encode( $summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES );
			if ( $json !== false ) {
				print $json . PHP_EOL;
			}
		}
	}

	private function runDeposit( string $path ): void {
		$depositId = $this->requireOption( 'deposit-id' );
		$depositObject  = $this->fetchDeposit( $depositId );
		$deposit = $depositObject->getDeposit();
		$this->writeDepositArtifacts( $path, $depositObject, $deposit );
	}

	private function writeDepositArtifacts( string $path, Deposit $depositObject, array $deposit ): bool {
		$depositId = $depositObject->getId();

		$donations = $this->fetchDonationsForDeposit( $depositId );
		$depositObject->setDonations( $donations );

		if ( $this->auditFileExists( $depositObject ) ) {
			Logger::info(
				'Skipping Chariot deposit because audit file already exists: ' . $depositId
			);
			return false;
		}

		if ( $donations === [] ) {
			$this->pendingDepositTracker->markPending( $depositId, 'No donations found for deposit yet' );
			Logger::warning( 'Chariot deposit pending: ' . $depositId . ' - no donations found yet' );
			return false;
		}

		$unknowns = $this->collectReportableUnknowns( $deposit, $donations );

		if ( $unknowns !== [] || $this->getOption( 'include-json' ) ) {
			$this->writeDepositJson( $path, $depositObject, $deposit, $donations );
		}
		$this->writeDepositAuditCsv( $path, $depositObject );
		$this->writeDepositUnknownsReport( $path, $depositObject, $unknowns );
		$this->pendingDepositTracker->markResolved( $depositId );

		return true;
	}

	/**
	 * List deposits.
	 *
	 * Chariot API docs:
	 * https://docs.givechariot.com/v2026-01-15/api/deposits/list?explorer=true
	 *
	 * @param string|null $token
	 * @return array
	 */
	private function fetchDepositsPage( ?string $token ): array {
		$params = [];

		$limit = $this->getLimitOption();
		if ( $limit !== null ) {
			$params['limit'] = $limit;
		}

		if ( $token !== null && $token !== '' ) {
			$params['page_token'] = $token;
		}

		$startDate = $this->getNormalizedDateOption( 'start-date' );
		if ( $startDate !== null ) {
			$params['settled_at.after'] = $startDate;
		}

		$endDate = $this->getNormalizedDateOption( 'end-date' );
		if ( $endDate !== null ) {
			$params['settled_at.before'] = $endDate;
		}

		return $this->api->listDeposits( $params );
	}

	/**
	 * Get a single deposit.
	 *
	 * Chariot API docs:
	 * https://docs.givechariot.com/v2026-01-15/api/deposits/get
	 *
	 * @param string $depositId
	 *
	 * @return \SmashPig\PaymentProviders\Chariot\Deposit
	 */
	private function fetchDeposit( string $depositId ): Deposit {
		return new Deposit( $this->api->getDeposit( $depositId ) );
	}

	/**
	 * List donations filtered by deposit_id.
	 *
	 * Chariot API docs:
	 * https://docs.givechariot.com/api/donations/list
	 *
	 * @param string $depositId
	 * @return array
	 */
	private function fetchDonationsForDeposit( string $depositId ): array {
		$api = $this->api;
		$result = $this->collectPagedResults(
			function ( ?string $token ) use ( $api, $depositId ): array {
				$params = [
					'deposit_id' => $depositId,
				];

				$limit = $this->getLimitOption();
				if ( $limit !== null ) {
					$params['limit'] = $limit;
				}

				if ( $token !== null && $token !== '' ) {
					$params['page_token'] = $token;
				}

				return $api->listDonations( $params );
			},
			'next_page_token',
			'nextPageToken'
		);

		return $result['results'];
	}

	/**
	 * Write the combined deposit and donations JSON payload.
	 *
	 * @param string $path
	 * @param \SmashPig\PaymentProviders\Chariot\Deposit $depositObject
	 * @param array $deposit
	 * @param array $donations
	 *
	 * @return void
	 */
	private function writeDepositJson( string $path, Deposit $depositObject, array $deposit, array $donations ): void {
		$payload = [
			'deposit' => $deposit,
			'donations' => $donations,
		];

		$this->emitJsonFile(
			$path,
			$depositObject->buildFilename( '', 'json' ),
			$payload
		);
	}

	/**
	 * Write the audit CSV for a deposit batch.
	 *
	 * @param string $path
	 * @param \SmashPig\PaymentProviders\Chariot\Deposit $depositObject
	 *
	 * @return void
	 * @throws \Exception
	 */
	private function writeDepositAuditCsv( string $path, Deposit $depositObject ): void {
		$rows = $this->buildAuditRows( $depositObject );
		$filename = $depositObject->buildFilename( '', 'csv' );
		$handle = fopen( $path . '/' . $filename, 'w' );
		if ( !$handle ) {
			throw new \RuntimeException( 'Unable to open deposit audit CSV file for writing.' );
		}

		fputcsv( $handle, self::AUDIT_CSV_COLUMNS, ",", '"', "\\" );
		foreach ( $rows as $row ) {
			foreach ( self::AUDIT_CSV_COLUMNS as $column ) {
				if ( isset( $row[$column] ) && is_array( $row[$column] ) ) {
					// Early warning against bugs sneaking in - fail hard.
					// This would generally happen when new code is not correct.
					throw new \Exception( $column . ' not expected to be an array ' . json_encode( $row[$column] ) );
				}
			}
		}
		foreach ( $rows as $row ) {
			fputcsv(
				$handle,
				array_map(
					static fn ( string $column ) => $row[$column] ?? '',
					self::AUDIT_CSV_COLUMNS
				),
				",",
				'"',
				"\\"
			);
		}

		fclose( $handle );
		Logger::info( 'Saved Chariot deposit audit CSV file to ' . $path . '/' . $filename );
	}

	/**
	 * Flatten a deposit into a payout audit row.
	 *
	 * @param \SmashPig\PaymentProviders\Chariot\Deposit $depositObject
	 *
	 * @return array
	 */
	private function flattenDepositPayoutRowForAuditCsv( Deposit $depositObject ): array {
		$paymentMethod = $this->getPaymentMethod( $depositObject );

		return [
			'gateway' => 'Chariot Disbursements',
			'audit_file_gateway' => 'Chariot Disbursements',
			'backend_processor' => $depositObject->getBackendProcessor(),
			'gateway_txn_id' => $depositObject->getId(),
			'backend_processor_txn_id' => $depositObject->getPaymentSourceId(),
			'settled_currency' => $depositObject->getCurrency(),
			'exchange_rate' => '1.000000',
			'settlement_batch_reference' => $depositObject->getSettlementBatchReference(),
			'settled_fee_amount' => $depositObject->getZeroAmountRounded(),
			'settled_net_amount' => $depositObject->getSettledAmount(),
			'settled_total_amount' => $depositObject->getSettledAmount(),
			'settled_date' => $depositObject->getSettledAt(),
			'date' => $depositObject->getCreatedAt(),
			'type' => 'payout',
			'payment_method' => $paymentMethod,
		];
	}

	/**
	 * Flatten a donation into an audit row.
	 *
	 * @param \SmashPig\PaymentProviders\Chariot\Deposit $depositObject
	 * @param \SmashPig\PaymentProviders\Chariot\Donation $donationObject
	 * @param array $donation
	 * @param float $exchangeRate
	 *
	 * @return array
	 */
	private function flattenDonationForAuditCsv( Deposit $depositObject, Donation $donationObject, array $donation, float $exchangeRate ): array {
		$properties = $donation['properties'] ?? [];
		$settledCurrency = $depositObject->getCurrency();
		$paymentMethod = $this->getPaymentMethod( $depositObject, $donation );

		return [
			'gateway' => 'Chariot Disbursements',
			'audit_file_gateway' => 'Chariot Disbursements',
			'backend_processor' => $donationObject->getPlatformName(),
			'gateway_txn_id' => $donation['id'],
			'backend_processor_txn_id' => (string)$donation['external_id'],
			'banking_institution' => $donationObject->getBankingInstitution(),
			'donor_advised_fund_name' => $donationObject->getDonorAdvisedFundName(),
			'original_currency' => $donationObject->getOriginalCurrency(),
			'settled_currency' => $settledCurrency,
			'settlement_batch_reference' => $depositObject->getSettlementBatchReference(),
			'settled_date' => $depositObject->getSettledAt(),
			'date' => $depositObject->getCreatedAt(),
			'original_fee_amount' => $donationObject->getOriginalFeeAmountRounded(),
			'original_net_amount' => $donationObject->getOriginalNetAmountRounded(),
			'original_total_amount' => $donationObject->getOriginalTotalAmountRounded(),
			'original_individual_gift_total_amount' => $donationObject->getOriginalIndividualGiftTotalAmountRounded(),
			'original_matching_gift_total_amount' => $donationObject->getOriginalMatchingGiftTotalAmountRounded(),
			'original_individual_gift_net_amount' => $donationObject->getOriginalIndividualGiftNetAmountRounded(),
			'original_matching_gift_net_amount' => $donationObject->getOriginalMatchingGiftNetAmountRounded(),
			'original_individual_gift_fee_amount' => $donationObject->getOriginalIndividualGiftFeeAmountRounded(),
			'original_matching_gift_fee_amount' => $donationObject->getOriginalMatchingGiftFeeAmountRounded(),
			'settled_individual_gift_total_amount' => $donationObject->getSettledIndividualGiftTotalAmountRounded( $exchangeRate, $settledCurrency ),
			'settled_matching_gift_total_amount' => $donationObject->getSettledMatchingGiftTotalAmountRounded( $exchangeRate, $settledCurrency ),
			'settled_individual_gift_net_amount' => $donationObject->getSettledIndividualGiftNetAmountRounded( $exchangeRate, $settledCurrency ),
			'settled_matching_gift_net_amount' => $donationObject->getSettledMatchingGiftNetAmountRounded( $exchangeRate, $settledCurrency ),
			'settled_individual_gift_fee_amount' => $donationObject->getSettledIndividualGiftFeeAmountRounded( $exchangeRate, $settledCurrency ),
			'settled_matching_gift_fee_amount' => $donationObject->getSettledMatchingGiftFeeAmountRounded( $exchangeRate, $settledCurrency ),
			'settled_fee_amount' => $donationObject->getSettledFeeAmountRounded( $exchangeRate, $settledCurrency ),
			'settled_net_amount' => $donationObject->getSettledNetAmountRounded( $exchangeRate, $settledCurrency ),
			'settled_total_amount' => $donationObject->getSettledTotalAmountRounded( $exchangeRate, $settledCurrency ),
			'exchange_rate' => number_format( $exchangeRate, 6, '.', '' ),
			'type' => 'donation',
			'is_daf' => $donationObject->isDonorAdvisedFundGrant(),
			'is_matching_gift' => $donationObject->isMatchingGift(),
			'matching_gift_organization' => $donationObject->getMatchingGiftOrganization(),
			'is_endowment' => !empty( $properties['Endowment flag?'] ) && $properties['Endowment flag?'] === 'Y',
			'first_name' => $donationObject->getFirstName(),
			'last_name' => $donationObject->getLastName(),
			'full_name' => $donationObject->getFullName(),
			'partner_full_name' => $donationObject->getPartnerName(),
			'prefix' => $donationObject->getPrefix(),
			'suffix' => $donationObject->getSuffix(),
			'email' => $donationObject->getEmail(),
			'phone' => $donationObject->getPhone(),
			'country' => $donationObject->getCountry(),
			'postal_code' => $donationObject->getPostalCode(),
			'state_province' => $donationObject->getStateProvince(),
			'city' => $donationObject->getCity(),
			'street_address' => $donationObject->getStreetAddress(),
			'supplemental_address_1' => $donationObject->getSupplementalAddress(),
			'payment_method' => $paymentMethod,
			'note' => $donationObject->getNote(),
			'dafpay_frequency' => $donationObject->getDafPayFrequency(),
			'dafpay_tracking_id' => $donationObject->getDafPayTrackingId(),
			'dafpay_type' => $donationObject->getDafPayType(),
			'dafpay_url' => $donationObject->getDafPayUrl(),
			'gift_source' => $donationObject->getGiftSource(),
			'direct_mail_appeal' => $donationObject->getAppeal(),
			'check_number' => $donationObject->getCheckNumber() ?: $depositObject->getCheckNumber(),
		];
	}

	/**
	 * Build a fee row for FX rounding adjustments.
	 *
	 * @param \SmashPig\PaymentProviders\Chariot\Deposit $depositObject
	 * @param string $roundedAmount
	 *
	 * @return array
	 */
	private function buildRoundingFeeRow( Deposit $depositObject, string $roundedAmount ): array {
		$depositCurrency = $depositObject->getCurrency();

		return [
			'gateway' => 'Chariot Disbursements',
			'gateway_txn_id' => $depositObject->getId() . '_rounding',
			'audit_file_gateway' => 'Chariot Disbursements',
			'backend_processor' => $depositObject->getBackendProcessor(),
			'backend_processor_txn_id' => $depositObject->getId() . '_rounding',
			'currency' => '',
			'original_currency' => '',
			'settled_currency' => $depositCurrency,
			'exchange_rate' => '1.000000',
			'settlement_batch_reference' => $depositObject->getSettlementBatchReference(),
			'original_fee_amount' => '',
			'original_net_amount' => '',
			'original_total_amount' => '',
			'original_individual_gift_total_amount' => '',
			'original_matching_gift_total_amount' => '',
			'original_combined_amount' => '',
			'settled_fee_amount' => $roundedAmount,
			'settled_net_amount' => $roundedAmount,
			'settled_total_amount' => $depositObject->getZeroAmountRounded(),
			'settled_date' => $depositObject->getSettledAt(),
			'date' => $depositObject->getCreatedAt(),
			'type' => 'fee',
			'first_name' => '',
			'last_name' => '',
			'full_name' => '',
			'partner_full_name' => '',
			'donor_advised_fund_organization' => '',
			'prefix' => '',
			'suffix' => '',
			'email' => '',
			'phone' => '',
			'country' => '',
			'postal_code' => '',
			'state_province' => '',
			'city' => '',
			'street_address' => '',
			'supplemental_address_1' => '',
			'payment_method' => '',
			'note' => self::ROUNDING_FEE_NOTE,
		];
	}

	/**
	 * Collect unknown paths from a deposit and its donations.
	 *
	 * @param array $deposit
	 *
	 * @return array
	 */
	private function depositUnknowns( array $deposit ): array {
		$collector = new UnknownPathCollector();
		$collector->scanDeposit( $deposit, ChariotObjectMetadata::getKnownDepositPaths() );
		$unknowns = $collector->getUnknownDepositPaths();
		$this->rememberUnknownPaths( $unknowns, 'deposit' );
		return $unknowns;
	}

	/**
	 * Collect unknown paths from a deposit and its donations.
	 *
	 * @param array $donations
	 *
	 * @return array
	 */
	private function donationUnknowns( array $donations ): array {
		$collector = new UnknownPathCollector();
		foreach ( $donations as $donation ) {
			if ( is_array( $donation ) ) {
				$collector->scanDonation( $donation, ChariotObjectMetadata::getKnownDonationPaths() );
			}
		}
		$unknowns = $collector->getUnknownDonationPaths();
		$this->rememberUnknownPaths( $unknowns, 'donation' );
		return $unknowns;
	}

	/**
	 * Write the unknown-paths report when unknowns are present.
	 *
	 * @param string $path
	 * @param \SmashPig\PaymentProviders\Chariot\Deposit $depositObject
	 * @param array $unknowns
	 *
	 * @return void
	 */
	private function writeDepositUnknownsReport( string $path, Deposit $depositObject, array $unknowns ): void {
		if ( $unknowns === [] ) {
			return;
		}

		$payload = array_filter( [
			'unknown_deposit_paths' => array_values( $unknowns['deposit'] ?? [] ),
			'unknown_donations_paths' => array_values( $unknowns['donation'] ?? [] ),
		] );

		$this->emitJsonFile(
			$path,
			$depositObject->buildFilename( 'unknowns', 'json' ),
			$payload
		);
	}

	/**
	 * @param array $deposit
	 * @param array $donations
	 *
	 * @return array
	 */
	private function collectReportableUnknowns( array $deposit, array $donations ): array {
		$reportableUnknowns = [];
		$unknownCollections = [ 'deposit' => $this->depositUnknowns( $deposit ), 'donation' => $this->donationUnknowns( $donations ) ];
		foreach ( $unknownCollections as $type => $unknownCollection ) {
			foreach ( $unknownCollection as $unknown ) {
				$sample = $unknown['sample'] ?? null;

				if ( $sample === null || $sample === '' || $sample === [] ) {
					continue;
				}
				$reportableUnknowns[$type][] = $unknown;
			}
		}

		return $reportableUnknowns;
	}

	private function logUnknownPathsSummary(): void {
		$this->logUnknownPathsForType( 'deposit', $this->allUnknownDepositPaths );
		$this->logUnknownPathsForType( 'donation', $this->allUnknownDonationPaths );
	}

	private function logUnknownPathsForType( string $type, array $unknowns ): void {
		if ( $unknowns === [] ) {
			return;
		}

		ksort( $unknowns );

		Logger::warning(
			sprintf(
				'Chariot unknown %s paths: %s',
				$type,
				implode( ', ', array_keys( $unknowns ) )
			)
		);
	}

	private function rememberUnknownPaths( array $unknowns, string $type ): void {
		foreach ( $unknowns as $path => $unknown ) {
			if ( $type === 'deposit' ) {
				if ( !isset( $this->allUnknownDepositPaths[$path] ) ) {
					$this->allUnknownDepositPaths[$path] = $unknown;
				}
				$this->allUnknownDepositPaths[$path] += $unknown['count'];
			} elseif ( $type === 'donation' ) {
				if ( !isset( $this->allUnknownDonationPaths[$path] ) ) {
					$this->allUnknownDonationPaths[$path] = $unknown;
				}
				$this->allUnknownDonationPaths[$path]['count'] += $unknown['count'];
			}
		}
	}

	/**
	 * Collect paginated results until exhausted or max-pages is reached.
	 *
	 * @param callable $loadPage
	 * @param string ...$tokenKeys
	 * @return array
	 */
	private function collectPagedResults( callable $loadPage, string ...$tokenKeys ): array {
		$results = [];
		$maxPages = $this->getMaxPagesOption();
		$token = null;
		$nextTokens = [];
		$page = 0;

		while ( true ) {
			$page++;
			if ( $maxPages !== null && $page > $maxPages ) {
				break;
			}

			$response = $loadPage( $token );
			$pageResults = $response['results'] ?? [];
			if ( !is_array( $pageResults ) ) {
				$pageResults = [];
			}
			$results = array_merge( $results, $pageResults );

			$token = null;
			foreach ( $tokenKeys as $tokenKey ) {
				if ( isset( $response[$tokenKey] ) && is_string( $response[$tokenKey] ) && $response[$tokenKey] !== '' ) {
					$token = $response[$tokenKey];
					$nextTokens[$tokenKey] = $token;
					break;
				}
			}

			if ( $token === null ) {
				break;
			}
		}

		return [
			'count' => count( $results ),
			'results' => $results,
			'next_tokens' => $nextTokens,
		];
	}

	/**
	 * Get the requested modes.
	 *
	 * @return array
	 */
	private function getRequestedModes(): array {
		$value = trim( (string)$this->getOption( 'mode' ) );
		$requested = array_values( array_filter( array_map( 'trim', explode( ',', $value ) ) ) );
		if ( !$requested ) {
			return [ self::MODE_DEPOSITS ];
		}

		$invalid = array_diff( $requested, self::VALID_MODES );
		if ( $invalid ) {
			throw new \InvalidArgumentException(
				'Invalid --mode value(s): ' . implode( ', ', $invalid ) .
				'. Valid values: ' . implode( ', ', self::VALID_MODES )
			);
		}

		return array_values( array_unique( $requested ) );
	}

	/**
	 * Get the optional list-call limit.
	 *
	 * @return int|null
	 */
	private function getLimitOption(): ?int {
		$value = trim( (string)$this->getOption( 'limit' ) );
		if ( $value === '' ) {
			return null;
		}

		$intValue = (int)$value;
		return $intValue > 0 ? $intValue : null;
	}

	/**
	 * Get the optional max-pages value.
	 *
	 * @return int|null
	 */
	private function getMaxPagesOption(): ?int {
		$value = trim( (string)$this->getOption( 'max-pages' ) );
		if ( $value === '' ) {
			return null;
		}

		$intValue = (int)$value;
		return $intValue > 0 ? $intValue : null;
	}

	/**
	 * Get a normalized UTC ISO-8601 timestamp for a CLI option.
	 *
	 * @param string $name
	 * @return string|null
	 */
	private function getNormalizedDateOption( string $name ): ?string {
		$value = trim( (string)$this->getOption( $name ) );
		if ( $value === '' ) {
			return null;
		}

		$timestamp = strtotime( $value );
		if ( $timestamp === false ) {
			throw new \InvalidArgumentException( sprintf( 'Invalid date for --%s: %s', $name, $value ) );
		}

		return gmdate( 'Y-m-d\TH:i:s\Z', $timestamp );
	}

	/**
	 * Require an option value.
	 *
	 * @param string $name
	 * @return string
	 */
	private function requireOption( string $name ): string {
		$value = trim( (string)$this->getOption( $name ) );
		if ( $value === '' ) {
			throw new \InvalidArgumentException( sprintf( 'Missing required --%s option', $name ) );
		}
		return $value;
	}

	public function getPaymentMethod( Deposit $deposit, array $donation = [] ): string {
		if ( !empty( $donation['dafpay_url'] ) ) {
			return 'DAFpay';
		}
		return $deposit->getPaymentMethod();
	}

	/**
	 * @param \SmashPig\PaymentProviders\Chariot\Deposit $depositObject
	 *
	 * @return array
	 */
	private function buildAuditRows( Deposit $depositObject ): array {
		$donations = $depositObject->getDonations();
		$exchangeRate = $depositObject->getExchangeRate();

		$rows = [];
		foreach ( $donations as $donation ) {
			if ( is_array( $donation ) ) {
				$donationObject = new Donation( $donation );
				$rows[] = $this->flattenDonationForAuditCsv( $depositObject, $donationObject, $donation, $donationObject->getExchangeRate() ?: $exchangeRate );
			}
		}

		$convertedNetMinorSum = 0;
		foreach ( $rows as $row ) {
			if ( ( $row['type'] ?? '' ) === 'donation' ) {
				$rounded = CurrencyRoundingHelper::getAmountInMinorUnits( $row['settled_net_amount'], $row['settled_currency'] );
				$convertedNetMinorSum += $rounded;
			}
		}

		$depositNetMinor = $depositObject->getSettledAmountInMinorUnits();
		$deltaMinor = $depositNetMinor - $convertedNetMinorSum;
		// Adjust by no more than .5 cents per donation - to allow for them all to err the same way.
		$maximumRoundingAdjustment = count( $donations ) / 2;

		if ( abs( $deltaMinor ) > $maximumRoundingAdjustment ) {
			throw new \RuntimeException(
				sprintf(
					'FX rounding adjustment of %d minor units exceeds maximum allowed %d for deposit %s',
					$deltaMinor,
					$maximumRoundingAdjustment,
					$depositObject->getId(),
				)
			);
		}

		if ( $deltaMinor !== 0 ) {
			$rows[] = $this->buildRoundingFeeRow( $depositObject, CurrencyRoundingHelper::getAmountInMajorUnits( $deltaMinor, $depositObject->getCurrency() ) );
		}

		$rows[] = $this->flattenDepositPayoutRowForAuditCsv( $depositObject );
		return $rows;
	}

	/**
	 * Emit a JSON file to disk.
	 *
	 * @param string $path
	 * @param string $filename
	 * @param array $payload
	 * @return void
	 */
	private function emitJsonFile( string $path, string $filename, array $payload ): void {
		$json = json_encode( $payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES );
		if ( $json === false ) {
			throw new \RuntimeException( 'Unable to encode JSON payload' );
		}

		$fullPath = $path . '/' . $filename;
		$result = file_put_contents( $fullPath, $json . PHP_EOL );
		if ( $result === false ) {
			throw new \RuntimeException( 'Unable to write JSON file: ' . $fullPath );
		}

		Logger::info( 'Saved Chariot JSON file to ' . $fullPath );
	}

	/**
	 * @param mixed $field
	 *
	 * @return float
	 */
	public function getAmount( string $field ): float {
		$totalMinor = (float)( $field ?? 0 );
		return $totalMinor / 100;
	}

	private function retryPendingDeposits( string $path, array $alreadyAttemptedIds ): void {
		foreach ( $this->pendingDepositTracker->getPendingDepositIds() as $depositId ) {
			if ( in_array( $depositId, $alreadyAttemptedIds, true ) ) {
				continue;
			}

			$depositObject = $this->fetchDeposit( $depositId );
			$this->writeDepositArtifacts(
				$path,
				$depositObject,
				$depositObject->getDeposit()
			);
		}
	}

	/**
	 * @return array|mixed
	 * @throws \Psr\Container\ContainerExceptionInterface
	 * @throws \Psr\Container\NotFoundExceptionInterface
	 */
	private function getIncomingPath(): mixed {
		return $this->config->get( 'reports_incoming_path' );
	}

	private function auditFileExists( Deposit $depositObject ): bool {
		$filename = $depositObject->buildFilename( '', 'csv' );

		foreach ( $this->getReportPaths() as $path ) {
			if ( file_exists( $path . '/' . $filename )
			  || file_exists( $path . '/' . $filename . '.gz' )
			) {
				return true;
			}
		}

		return false;
	}

	private function getReportPaths(): array {
		$incoming = $this->getIncomingPath();

		return [
			$incoming,
			str_replace( 'incoming', 'completed', $incoming ),
			str_replace( 'incoming', 'ignored', $incoming ),
		];
	}

}

$maintClass = GetReport::class;
require RUN_MAINTENANCE_IF_MAIN;
