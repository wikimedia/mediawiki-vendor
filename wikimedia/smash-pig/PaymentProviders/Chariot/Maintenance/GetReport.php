<?php

namespace SmashPig\PaymentProviders\Chariot\Maintenance;

use SmashPig\Core\Context;
use SmashPig\Core\Helpers\CurrencyRoundingHelper;
use SmashPig\Core\Logging\Logger;
use SmashPig\Core\ProviderConfiguration;
use SmashPig\Maintenance\MaintenanceBase;
use SmashPig\PaymentProviders\Chariot\Api;
use SmashPig\PaymentProviders\Chariot\Deposit;
use SmashPig\PaymentProviders\Chariot\Donation;

require __DIR__ . '/../../../Maintenance/MaintenanceBase.php';

class GetReport extends MaintenanceBase {

	private const MODE_DEPOSITS = 'deposits';
	private const MODE_DEPOSIT = 'deposit';

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
		'original_matching_gift_amount',
		'original_individual_gift_amount',
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
	];

	/**
	 * List of known paths in the json, if more are added an 'unknowns' file will be generated.
	 *
	 * We track these so that if additional columns are added we 'notice'.
	 */
	private const KNOWN_DEPOSIT_PATHS = [
		'id',
		'created_at',
		'bank_created_at',
		'updated_at',
		'status',
		'payment_source_id',
		'settled_at',
		'returned_at',
		'properties',
		'properties.CRM status',
		'properties.Journaled in Sage',
		'properties.Gift Type',
		// We see lockbox_id and mail_item_id coming in from Digital Mailbox
		'lockbox_id',
		'mail_item_id',
		'transfer',
		'transfer.amount',
		'transfer.currency',
		'transfer.financial_account_id',
		'transfer.description',
		'transfer.inbound_account_transfer',
		'transfer.inbound_account_transfer.created_at',
		'transfer.inbound_ach_transfer',
		'transfer.inbound_ach_transfer.standard_entry_class_code',
		'transfer.inbound_ach_transfer.company_entry_description',
		'transfer.inbound_ach_transfer.originator_routing_number',
		'transfer.inbound_ach_transfer.originator_company_name',
		'transfer.inbound_ach_transfer.trace_number',
		'transfer.inbound_ach_transfer.effective_date',
		'transfer.inbound_ach_transfer.status',
		'transfer.inbound_ach_transfer.receiver_id',
		'transfer.check_deposit',
		'transfer.check_deposit.auxiliary_on_us',
		'transfer.check_deposit.routing_number',
		'transfer.check_deposit.submitted_at',
		'transfer.check_deposit.status',
		'transfer.type',
	];

	/**
	 * Fields that relate to donations.
	 *
	 * We track these so that if additional columns are added we 'notice'.
	 */
	private const KNOWN_DONATION_PATHS = [
		'id',
		'created_at',
		'updated_at',
		'currency',
		'amount_gross',
		'amount_fee',
		'amount_net',
		'individual_gift_amount',
		'match_amount',
		'payment_status',
		'payment_source_id',
		'external_id',
		'note',
		'purpose',
		'artifacts',
		'attribution',
		'attribution.primary_donor',
		'attribution.primary_donor.full_name',
		'attribution.primary_donor.first_name',
		'attribution.primary_donor.last_name',
		'attribution.primary_donor.email',
		'attribution.primary_donor.prefix',
		'attribution.primary_donor.address',
		'attribution.primary_donor.address.line1',
		'attribution.primary_donor.address.line2',
		'attribution.primary_donor.address.city',
		'attribution.primary_donor.address.state',
		'attribution.primary_donor.address.postal_code',
		'attribution.primary_donor.address.country',
		'attribution.joint_donor',
		'attribution.joint_donor.email',
		'attribution.joint_donor.full_name',
		'donor_advised_fund_grant',
		'donor_advised_fund_grant.donor_fund_name',
		'donor_advised_fund_grant.organization_name',
		'donor_advised_fund_grant.program_name',
		'donor_advised_fund_grant.sponsor_grant_id',
		'platform',
		'platform.acceptance',
		'platform.acceptance.accepted',
		'platform.acceptance.expires_at',
		'platform.name',
		'platform.platform_grant_id',
		'platform.metadata',
		'platform.metadata.contributionId',
		'platform.metadata.donorId',
		'platform.metadata.nonprofitId',
		'platform.metadata.Payable To',
		'platform.metadata.Recommended By',
		'platform.metadata.Description',
		'platform.metadata.Activity',
		'platform.metadata.Disbursement ID',
		'platform.metadata.Disbursing Entity',
		'platform.metadata.Fee Comment',
		'platform.metadata.Frequency',
		'platform.metadata.Project',
		'platform.metadata.Project Remote ID',
		'platform.metadata.Reason',
		'platform.metadata.Acknowledgement',
		'platform.metadata.Confirmation Number',
		'platform.metadata.Disbursement Method',
		'platform.metadata.Distribution',
		'properties',
		'properties.Campaign',
		'properties.Country',
		'properties.Partner',
		'properties.Prefix',
		'properties.Suffix',
		'properties.Review status',
		'properties.Journaled in Sage',
		'properties.Groundswell Company Name',
		'properties.Marked for export',
		'properties.Endowment flag?',
		'properties.CRM status',
		'settlement',
		'settlement.deposit_id',
		'settlement.received_at',
		'settlement.settled_at',
		'donor_email',
		'donor_phone',
		'assignee',
		'crm_status',
		'groundswell_company_name',
		'internal_note',
		'partner',
		'partner_full_name',
		'prefix',
		'suffix',
		'received_offline_on',
		'review_status',
		'dafpay_form',
		'dafpay_frequency',
		'dafpay_tracking_id',
		'dafpay_type',
		'dafpay_url',
		'corporate_match',
		'corporate_match.company_name',
		'corporate_match.match_amount',
		'corporate_match.program_name',
		'corporate_match.source',
		// We see lockbox_id and mail_item_id coming in from Digital Mailbox
		'lockbox_id',
		'mail_item_id',
	];

	private ProviderConfiguration $config;

	/**
	 * Accumulator for unknown paths during scanning.
	 *
	 * @var array<string,array<string,mixed>>
	 */
	private array $unknownPaths = [];

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
		$path = $this->config->get( 'reports_incoming_path' );
		if ( !is_dir( $path ) ) {
			throw new \RuntimeException( 'Output directory does not exist: ' . $path );
		}

		$api = new Api();

		foreach ( $this->getRequestedModes() as $mode ) {
			switch ( $mode ) {
				case self::MODE_DEPOSITS:
					$this->runDeposits( $api, $path );
					break;
				case self::MODE_DEPOSIT:
					$this->runDeposit( $api, $path );
					break;
			}
		}
	}

	private function runDeposits( Api $api, string $path ): void {
		$depositId = trim( (string)$this->getOption( 'deposit-id' ) );
		if ( $depositId !== '' ) {
			$depositObject = $this->fetchDeposit( $api, $depositId );
			$deposit = $depositObject->getDeposit();
			$this->writeDepositArtifacts( $api, $path, $depositObject, $deposit );

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
			fn ( ?string $token ): array => $this->fetchDepositsPage( $api, $token ),
			'next_page_token',
			'nextPageToken'
		);

		$writtenIds = [];
		foreach ( $result['results'] as $deposit ) {
			if ( !is_array( $deposit ) ) {
				continue;
			}
			$depositObject = new Deposit( $deposit );
			$this->writeDepositArtifacts( $api, $path, $depositObject, $deposit );
			$writtenIds[] = $depositObject->getId();
		}

		if ( $this->getOption( 'stdout' ) ) {
			$summary = [
				'mode' => self::MODE_DEPOSITS,
				'count' => count( $writtenIds ),
				'next_tokens' => $result['next_tokens'],
				'deposit_ids' => $writtenIds,
			];
			$json = json_encode( $summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES );
			if ( $json !== false ) {
				print $json . PHP_EOL;
			}
		}
	}

	private function runDeposit( Api $api, string $path ): void {
		$depositId = $this->requireOption( 'deposit-id' );
		$depositObject  = $this->fetchDeposit( $api, $depositId );
		$deposit = $depositObject->getDeposit();
		$this->writeDepositArtifacts( $api, $path, $depositObject, $deposit );
	}

	private function writeDepositArtifacts( Api $api, string $path, Deposit $depositObject, array $deposit ): void {
		$donations = $this->fetchDonationsForDeposit( $api, $depositObject->getId() );
		$fileSuffix = $this->buildDepositFileSuffix( $depositObject, $deposit, $donations );
		$unknowns = $this->collectDepositUnknowns( $deposit, $donations );
		$timestamp = $depositObject->getDepositTimestampForFilename();

		if ( $unknowns !== [] || $this->getOption( 'include-json' ) ) {
			$this->writeDepositJson( $path, $fileSuffix, $timestamp, $deposit, $donations );
		}

		$this->writeDepositAuditCsv( $path, $fileSuffix, $timestamp, $deposit, $donations );
		$this->writeDepositUnknownsReport( $path, $fileSuffix, $timestamp, $unknowns );
	}

	/**
	 * List deposits.
	 *
	 * Chariot API docs:
	 * https://docs.givechariot.com/v2026-01-15/api/deposits/list?explorer=true
	 *
	 * @param Api $api
	 * @param string|null $token
	 * @return array
	 */
	private function fetchDepositsPage( Api $api, ?string $token ): array {
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

		return $api->listDeposits( $params );
	}

	/**
	 * Get a single deposit.
	 *
	 * Chariot API docs:
	 * https://docs.givechariot.com/v2026-01-15/api/deposits/get
	 *
	 * @param Api $api
	 * @param string $depositId
	 *
	 * @return \SmashPig\PaymentProviders\Chariot\Deposit
	 */
	private function fetchDeposit( Api $api, string $depositId ): Deposit {
		return new Deposit( $api->getDeposit( $depositId ) );
	}

	/**
	 * List donations filtered by deposit_id.
	 *
	 * Chariot API docs:
	 * https://docs.givechariot.com/api/donations/list
	 *
	 * @param Api $api
	 * @param string $depositId
	 * @return array
	 */
	private function fetchDonationsForDeposit( Api $api, string $depositId ): array {
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
	 * @param string $suffix
	 * @param string $timestamp
	 * @param array $deposit
	 * @param array $donations
	 * @return void
	 */
	private function writeDepositJson( string $path, string $suffix, string $timestamp, array $deposit, array $donations ): void {
		$payload = [
			'deposit' => $deposit,
			'donations' => $donations,
		];

		$this->emitJsonFile(
			$path,
			$this->buildFilename( '', $suffix, 'json', $timestamp ),
			$payload
		);
	}

	/**
	 * Write the audit CSV for a deposit batch.
	 *
	 * @param string $path
	 * @param string $suffix
	 * @param string $timestamp
	 * @param array $deposit
	 * @param array $donations
	 * @return void
	 */
	private function writeDepositAuditCsv( string $path, string $suffix, string $timestamp, array $deposit, array $donations ): void {
		$rows = $this->buildAuditRows( $deposit, $donations );
		$filename = $this->buildFilename( '', $suffix, 'csv', $timestamp );
		$handle = fopen( $path . '/' . $filename, 'w' );
		if ( !$handle ) {
			throw new \RuntimeException( 'Unable to open deposit audit CSV file for writing.' );
		}

		fputcsv( $handle, self::AUDIT_CSV_COLUMNS );
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
				)
			);
		}

		fclose( $handle );
		Logger::info( 'Saved Chariot deposit audit CSV file to ' . $path . '/' . $filename );
	}

	/**
	 * Flatten a deposit into a payout audit row.
	 *
	 * @param array $deposit
	 * @param array $donations
	 * @return array
	 */
	private function flattenDepositPayoutRowForAuditCsv( array $deposit, array $donations ): array {
		$depositObject = new Deposit( $deposit );
		$paymentMethod = $this->getPaymentMethod( $deposit );
		$transfer = $deposit['transfer'];
		$currency = $depositObject->getCurrency();
		$backendProcessor = $this->getDepositBackendProcessor( $deposit, $donations );
		$amount = $this->getAmount( $transfer['amount'] );

		return [
			'gateway' => 'Chariot Disbursements',
			'audit_file_gateway' => 'Chariot Disbursements',
			'backend_processor' => $backendProcessor,
			'gateway_txn_id' => $depositObject->getId(),
			'backend_processor_txn_id' => $depositObject->getPaymentSourceId(),
			'settled_currency' => $currency,
			'exchange_rate' => '1.000000',
			'settlement_batch_reference' => $depositObject->getSettlementBatchReference(),
			'settled_fee_amount' => $this->round( 0.0, $currency ),
			'settled_net_amount' => $this->round( $amount, $currency ),
			'settled_total_amount' => $this->round( $amount, $currency ),
			'settled_date' => $depositObject->getSettledAt(),
			'date' => $depositObject->getCreatedAt(),
			'type' => 'payout',
			'payment_method' => $paymentMethod,
		];
	}

	/**
	 * Flatten a donation into an audit row.
	 *
	 * @param array $deposit
	 * @param array $donation
	 * @param float $exchangeRate
	 * @return array
	 */
	private function flattenDonationForAuditCsv( array $deposit, array $donation, float $exchangeRate ): array {
		$donationObject = new Donation( $donation );
		$depositObject = new Deposit( $deposit );
		$daf = $donation['donor_advised_fund_grant'] ?? [];
		$matchingGift = $donation['corporate_match'] ?? [];
		$platform = $donation['platform'] ?? [];
		$properties = $donation['properties'] ?? [];
		$originalCurrency = $donation['currency'];
		$settledCurrency = $this->getDepositCurrency( $deposit );
		$paymentMethod = $this->getPaymentMethod( $deposit, $donation );

		return [
			'gateway' => 'Chariot Disbursements',
			'audit_file_gateway' => 'Chariot Disbursements',
			'backend_processor' => (string)( $platform['name'] ?? '' ),
			'gateway_txn_id' => $donation['id'],
			'backend_processor_txn_id' => (string)$donation['external_id'],
			'banking_institution' => trim( (string)( $daf['organization_name'] ?? '' ) ),
			'donor_advised_fund_name' => $daf['donor_fund_name'] ?? '',
			'original_currency' => $originalCurrency,
			'settled_currency' => $settledCurrency,
			'settlement_batch_reference' => $depositObject->getSettlementBatchReference(),
			'settled_date' => $depositObject->getSettledAt(),
			'date' => $depositObject->getCreatedAt(),
			'original_fee_amount' => $this->getRoundedAmount( $donation['amount_fee'], $originalCurrency ),
			'original_net_amount' => $this->getRoundedAmount( $donation['amount_net'], $originalCurrency ),
			'original_total_amount' => $this->getRoundedAmount( $donation['amount_gross'], $originalCurrency ),
			'original_individual_gift_amount' => $this->getAmount( $donation['individual_gift_amount'] ?? 0 ),
			'original_matching_gift_amount' => $this->getAmount( $matchingGift['match_amount'] ?? 0 ),
			'settled_fee_amount' => $this->getAmount( $donation['amount_fee'] ) * $exchangeRate,
			'settled_net_amount' => $this->getAmount( $donation['amount_net'] ) * $exchangeRate,
			'settled_total_amount' => $this->getAmount( $donation['amount_gross'] ) * $exchangeRate,
			'exchange_rate' => number_format( $exchangeRate, 6, '.', '' ),
			'type' => 'donation',
			'is_daf' => !empty( $daf['donor_fund_name'] ),
			'is_matching_gift' => !empty( $matchingGift ),
			'matching_gift_organization' => $matchingGift['company_name'] ?? '',
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
			'dafpay_frequency' => $donation['dafpay_frequency'] ?? '',
			'dafpay_tracking_id' => $donation['dafpay_tracking_id'] ?? '',
			'dafpay_type' => $donation['dafpay_type'] ?? '',
			'dafpay_url' => $donation['dafpay_url'] ?? '',
			'gift_source' => $donationObject->getGiftSource(),
		];
	}

	/**
	 * Build a fee row for FX rounding adjustments.
	 *
	 * @param array $deposit
	 * @param int $deltaMinor
	 * @return array
	 */
	private function buildRoundingFeeRow( array $deposit, int $deltaMinor, array $donations ): array {
		$depositObject = new Deposit( $deposit );
		$depositCurrency = $depositObject->getCurrency();
		$negativeDeltaMinor = -1 * $deltaMinor;
		$backendProcessor = $this->getDepositBackendProcessor( $deposit, $donations );
		return [
			'gateway' => 'Chariot Disbursements',
			'audit_file_gateway' => 'Chariot Disbursements',
			'backend_processor' => $backendProcessor,
			'backend_processor_txn_id' => $depositObject->getId() . '_rounding',
			'currency' => $depositCurrency,
			'original_currency' => $depositCurrency,
			'settled_currency' => $depositCurrency,
			'exchange_rate' => '1.000000',
			'settlement_batch_reference' => $depositObject->getSettlementBatchReference(),
			'original_fee_amount' => $this->round( $deltaMinor, $depositCurrency ),
			'original_net_amount' => $this->round( $negativeDeltaMinor, $depositCurrency ),
			'original_total_amount' => $this->round( 0, $depositCurrency ),
			'original_matching_gift_total_amount' => $this->round( 0, $depositCurrency ),
			'original_combined_amount' => $this->round( 0, $depositCurrency ),
			'settled_fee_amount' => $this->round( $deltaMinor, $depositCurrency ),
			'settled_net_amount' => $this->round( $negativeDeltaMinor, $depositCurrency ),
			'settled_total_amount' => $this->round( 0, $depositCurrency ),
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
	 * Determine the backend processor for a deposit batch.
	 *
	 * @param array $deposit
	 * @param array $donations
	 * @return string
	 */
	private function getDepositBackendProcessor( array $deposit, array $donations ): string {
		$values = [];

		foreach ( $donations as $donation ) {
			if ( !is_array( $donation ) ) {
				continue;
			}
			$platformName = trim( (string)( $donation['platform']['name'] ?? '' ) );
			$orgName = trim( (string)( $donation['donor_advised_fund_grant']['organization_name'] ?? '' ) );

			if ( $platformName !== '' ) {
				$values[] = $platformName;
			} elseif ( $orgName !== '' ) {
				$values[] = $orgName;
			}
		}

		$values = array_values( array_unique( $values ) );
		if ( count( $values ) === 1 ) {
			return $values[0];
		}

		$transfer = is_array( $deposit['transfer'] ?? null ) ? $deposit['transfer'] : [];
		$ach = is_array( $transfer['inbound_ach_transfer'] ?? null ) ? $transfer['inbound_ach_transfer'] : [];
		return (string)( $ach['originator_company_name'] ?? '' );
	}

	/**
	 * Get the deposit total for filenames.
	 *
	 * @param array $deposit
	 * @return string
	 */
	private function getDepositTotalForFilename( array $deposit ): string {
		$amount = $deposit['transfer']['amount'] ?? 0;
		$currency = $this->getDepositCurrency( $deposit );
		return $this->round( $amount, $currency );
	}

	/**
	 * Build the per-deposit filename suffix.
	 *
	 * @param \SmashPig\PaymentProviders\Chariot\Deposit $depositObject
	 * @param array $deposit
	 * @param array $donations
	 *
	 * @return string
	 */
	private function buildDepositFileSuffix( Deposit $depositObject, array $deposit, array $donations ): string {
		$parts = [];

		$backendProcessor = trim( $this->getDepositBackendProcessor( $deposit, $donations ) );
		if ( $backendProcessor !== '' ) {
			$parts[] = $backendProcessor;
		}

		$parts[] = $this->getDepositTotalForFilename( $deposit );
		$parts[] = $depositObject->getId();

		return implode( '-', $parts );
	}

	/**
	 * Collect unknown paths from a deposit and its donations.
	 *
	 * @param array $deposit
	 * @param array $donations
	 * @return array
	 */
	private function collectDepositUnknowns( array $deposit, array $donations ): array {
		$this->unknownPaths = [];

		$this->scanUnknownPaths( $deposit, '', self::KNOWN_DEPOSIT_PATHS );
		foreach ( $donations as $donation ) {
			if ( is_array( $donation ) ) {
				$this->scanUnknownPaths( $donation, '', self::KNOWN_DONATION_PATHS );
			}
		}

		ksort( $this->unknownPaths );
		return $this->unknownPaths;
	}

	/**
	 * Write the unknown-paths report when unknowns are present.
	 *
	 * @param string $path
	 * @param string $suffix
	 * @param string $timestamp
	 * @param array $unknowns
	 * @return void
	 */
	private function writeDepositUnknownsReport( string $path, string $suffix, string $timestamp, array $unknowns ): void {
		if ( $unknowns === [] ) {
			return;
		}

		$payload = [
			'known_deposit_paths' => self::KNOWN_DEPOSIT_PATHS,
			'known_donation_paths' => self::KNOWN_DONATION_PATHS,
			'handled_audit_columns' => self::AUDIT_CSV_COLUMNS,
			'unknown_paths' => array_values( $unknowns ),
		];

		$this->emitJsonFile(
			$path,
			$this->buildFilename( 'unknowns', $suffix, 'json', $timestamp ),
			$payload
		);
	}

	/**
	 * Scan for unknown paths in a nested payload.
	 *
	 * @param mixed $value
	 * @param string $path
	 * @param array $knownPaths
	 * @return void
	 */
	private function scanUnknownPaths( $value, string $path, array $knownPaths ): void {
		if ( is_array( $value ) ) {
			if ( $this->isListArray( $value ) ) {
				$listPath = $path === '' ? '[]' : $path;
				if ( !in_array( $listPath, $knownPaths, true ) ) {
					$this->noteUnknownPath( $listPath, $value[0] ?? null );
				}
				foreach ( $value as $item ) {
					if ( is_array( $item ) ) {
						foreach ( $item as $key => $itemValue ) {
							$childPath = $listPath . '[].' . $key;
							$this->scanUnknownPaths( $itemValue, $childPath, $knownPaths );
						}
					}
				}
				return;
			}

			if ( $path !== '' && !in_array( $path, $knownPaths, true ) ) {
				$this->noteUnknownPath( $path, $value );
			}
			foreach ( $value as $key => $child ) {
				$childPath = $path === '' ? (string)$key : $path . '.' . $key;
				$this->scanUnknownPaths( $child, $childPath, $knownPaths );
			}
			return;
		}

		if ( $path !== '' && !in_array( $path, $knownPaths, true ) ) {
			$this->noteUnknownPath( $path, $value );
		}
	}

	/**
	 * Record an unknown path and sample value.
	 *
	 * @param string $path
	 * @param mixed $sample
	 * @return void
	 */
	private function noteUnknownPath( string $path, $sample ): void {
		if ( !isset( $this->unknownPaths[$path] ) ) {
			$this->unknownPaths[$path] = [
				'path' => $path,
				'count' => 0,
				'sample' => $this->sampleValue( $sample ),
			];
		}
		$this->unknownPaths[$path]['count']++;
	}

	/**
	 * Create a sample value for an unknown-path report.
	 *
	 * @param mixed $value
	 * @return mixed
	 */
	private function sampleValue( $value ) {
		if ( is_array( $value ) ) {
			return $value;
		}
		if ( is_bool( $value ) ) {
			return $value;
		}
		if ( $value === null ) {
			return null;
		}
		return (string)$value;
	}

	/**
	 * Determine whether an array is a list array.
	 *
	 * @param array $value
	 * @return bool
	 */
	private function isListArray( array $value ): bool {
		return array_keys( $value ) === range( 0, count( $value ) - 1 );
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

	/**
	 * Get the deposit transfer currency.
	 *
	 * @param array $deposit
	 * @return string
	 */
	private function getDepositCurrency( array $deposit ): string {
		return ( new Deposit( $deposit ) )->getCurrency();
	}

	/**
	 * Calculate a batch exchange rate from the summed original donation net
	 * amounts and the deposit payout amount.
	 *
	 * @param array $deposit
	 * @param array $donations
	 * @return float
	 */
	private function getBatchExchangeRate( array $deposit, array $donations ): float {
		$depositNetMinor = $deposit['transfer']['amount'] ?? null;
		if ( !is_numeric( $depositNetMinor ) ) {
			throw new \RuntimeException( 'Deposit transfer amount is missing or non-numeric' );
		}

		$originalBatchNetMinor = 0.0;
		foreach ( $donations as $donation ) {
			if ( !is_array( $donation ) ) {
				continue;
			}
			$net = $donation['amount_net'] ?? null;
			if ( is_numeric( $net ) ) {
				$originalBatchNetMinor += (float)$net;
			}
		}

		if ( $originalBatchNetMinor <= 0.0 ) {
			throw new \RuntimeException( 'Cannot calculate exchange rate from zero donation net total' );
		}

		return (float)$depositNetMinor / $originalBatchNetMinor;
	}

	/**
	 * Round a minor-unit amount into a decimal string for a currency.
	 *
	 * @param mixed $amount
	 * @param string $currency
	 *
	 * @return string
	 */
	private function round( float $amount, string $currency ): string {
		return CurrencyRoundingHelper::round( (float)$amount, $currency );
	}

	public function getPaymentMethod( array $deposit, array $donation = [] ): string {
		if ( !empty( $donation['dafpay_url'] ) ) {
			return 'DAFpay';
		}
		return ( new Deposit( $deposit ) )->getPaymentMethod();
	}

	/**
	 * @param array $deposit
	 * @param array $donations
	 *
	 * @return array
	 */
	private function buildAuditRows( array $deposit, array $donations ): array {
		$exchangeRate = $this->getBatchExchangeRate( $deposit, $donations );

		$rows = [];
		foreach ( $donations as $donation ) {
			if ( is_array( $donation ) ) {
				$rows[] = $this->flattenDonationForAuditCsv( $deposit, $donation, $exchangeRate );
			}
		}

		$convertedNetMinorSum = 0;
		foreach ( $rows as $row ) {
			if ( ( $row['type'] ?? '' ) === 'donation' ) {
				$rounded = (int)round( (float)( $row['original_net_amount'] * 100 * $exchangeRate ) );
				$convertedNetMinorSum += $rounded;
			}
		}

		$depositNetMinor = (int)( $deposit['transfer']['amount'] ?? 0 );
		$deltaMinor = $depositNetMinor - $convertedNetMinorSum;
		// Adjust by no more than .5 cents per donation - to allow for them all to err the same way.
		$maximumRoundingAdjustment = count( $donations ) / 2;

		$depositObject = new Deposit( $deposit );

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
			$rows[] = $this->buildRoundingFeeRow( $deposit, $deltaMinor, $donations );
		}

		$rows[] = $this->flattenDepositPayoutRowForAuditCsv( $deposit, $donations );
		return $rows;
	}

	/**
	 * Convert a minor-unit amount using an exchange rate and round it for the
	 * target currency.
	 *
	 * @param mixed $amountMinor
	 * @param float $exchangeRate
	 * @param string $currency
	 * @return string
	 */
	private function getConvertedAmount( $amountMinor, float $exchangeRate, string $currency ): string {
		if ( $amountMinor === null || $amountMinor === '' || !is_numeric( $amountMinor ) ) {
			return CurrencyRoundingHelper::round( 0, $currency );
		}

		$convertedMajor = ( (float)$amountMinor * $exchangeRate ) / 100;
		return CurrencyRoundingHelper::round( $convertedMajor, $currency );
	}

	/**
	 * Build an output filename.
	 *
	 * @param string $prefix
	 * @param string $suffix
	 * @param string $extension
	 * @param string $timestamp
	 * @return string
	 */
	private function buildFilename( string $prefix, string $suffix, string $extension, string $timestamp ): string {
		$parts = [];
		if ( $prefix !== '' ) {
			$parts[] = $prefix;
		}
		$parts[] = $timestamp;
		$parts[] = $suffix;

		$base = implode( '-', array_filter( $parts, static fn ( string $part ): bool => $part !== '' ) );
		$base = preg_replace( '/[^A-Za-z0-9._-]+/', '_', $base );
		$base = trim( (string)$base, '_-' );

		return $base . '.' . $extension;
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

	/**
	 * @param mixed $field
	 * @param string $settledCurrency
	 *
	 * @return float
	 */
	public function getRoundedAmount( string $field, string $settledCurrency ): float {
		$feeMinor = $this->getAmount( $field );
		return $this->round( $feeMinor, $settledCurrency );
	}
}

$maintClass = GetReport::class;
require RUN_MAINTENANCE_IF_MAIN;
