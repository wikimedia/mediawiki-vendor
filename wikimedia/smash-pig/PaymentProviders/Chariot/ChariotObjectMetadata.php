<?php

namespace SmashPig\PaymentProviders\Chariot;

class ChariotObjectMetadata {

	public const STATUS_USED = 'used';
	public const STATUS_IGNORED = 'ignored';

	/**
	 * List of known paths in the json, if more are added an 'unknowns' file will be generated.
	 *
	 * We track these so that if additional columns are added we 'notice'.
	 */
	private const DEPOSIT_FIELDS = [
		'id' => [
			'status' => self::STATUS_USED,
			'note' => 'This is the basis for the batch number'
		],
		'created_at' => [ 'status' => self::STATUS_USED ],
		'bank_created_at' => [],
		'updated_at' => [ 'status' => self::STATUS_USED ],
		'status' => [],
		'payment_source_id' => [ 'status' => self::STATUS_USED ],
		'settled_at' => [ 'status' => self::STATUS_USED ],
		'returned_at' => [],
		'properties.CRM status' => [],
		'properties.Journaled in Sage' => [],
		'properties.Gift Type' => [],
		'lockbox_id' => [
			'note' => 'Digital Mailbox field. Not important for us',
			'status' => self::STATUS_IGNORED,
		],
		'mail_item_id' => [
			'note' => 'Digital Mailbox field. Not important for us',
			'status' => self::STATUS_IGNORED,
		],
		'transfer.amount' => [
			'status' => self::STATUS_USED,
			'note' => 'Settled amount in minor currency units.',
		],
		'transfer.currency' => [ 'status' => self::STATUS_USED ],
		'transfer.financial_account_id' => [],
		'transfer.description' => [],
		'transfer.inbound_account_transfer.created_at' => [],
		'transfer.inbound_ach_transfer' => [ 'status' => self::STATUS_USED ],
		'transfer.inbound_ach_transfer.standard_entry_class_code' => [],
		'transfer.inbound_ach_transfer.company_entry_description' => [],
		'transfer.inbound_ach_transfer.originator_routing_number' => [],
		'transfer.inbound_ach_transfer.originator_company_name' => [ 'status' => self::STATUS_USED ],
		'transfer.inbound_ach_transfer.trace_number' => [],
		'transfer.inbound_ach_transfer.effective_date' => [],
		'transfer.inbound_ach_transfer.status' => [],
		'transfer.inbound_ach_transfer.receiver_id' => [],
		'transfer.check_deposit.auxiliary_on_us' => [
			'status' => self::STATUS_USED,
			'note' => 'Donor check number.',
		],
		'transfer.check_deposit.routing_number' => [],
		'transfer.check_deposit.submitted_at' => [],
		'transfer.check_deposit.status' => [],
		'transfer.type' => [],
	];

	/**
	 * List of known paths in the json, if more are added an 'unknowns' file will be generated.
	 *
	 * We track these so that if additional columns are added we 'notice'.
	 */
	private const DONATION_FIELDS = [
		'id' => [ 'status' => self::STATUS_USED ],
		'created_at' => [ 'status' => self::STATUS_USED ],
		'updated_at' => [],
		'currency' => [ 'status' => self::STATUS_USED ],
		'amount_gross' => [
			'status' => self::STATUS_USED,
			'note' => 'Original gross amount in minor currency units.',
		],
		'amount_fee' => [
			'status' => self::STATUS_USED,
			'note' => 'Original fee amount in minor currency units.',
		],
		'amount_net' => [
			'status' => self::STATUS_USED,
			'note' => 'Original net amount in minor currency units.',
		],
		'individual_gift_amount' => [ 'status' => self::STATUS_USED ],
		'match_amount' => [],
		'payment_status' => [],
		'payment_source_id' => [],
		'external_id' => [ 'status' => self::STATUS_USED ],
		'note' => [ 'status' => self::STATUS_USED ],
		'purpose' => [ 'status' => self::STATUS_USED, 'note' => 'used for note' ],
		'artifacts' => [],
		'attribution.primary_donor' => [],
		'attribution.primary_donor.full_name' => [ 'status' => self::STATUS_USED ],
		'attribution.primary_donor.first_name' => [ 'status' => self::STATUS_USED ],
		'attribution.primary_donor.last_name' => [ 'status' => self::STATUS_USED ],
		'attribution.primary_donor.email' => [ 'status' => self::STATUS_USED ],
		'attribution.primary_donor.prefix' => [ 'status' => self::STATUS_USED ],
		'attribution.primary_donor.address' => [],
		'attribution.primary_donor.address.line1' => [ 'status' => self::STATUS_USED ],
		'attribution.primary_donor.address.line2' => [ 'status' => self::STATUS_USED ],
		'attribution.primary_donor.address.city' => [ 'status' => self::STATUS_USED ],
		'attribution.primary_donor.address.state' => [ 'status' => self::STATUS_USED ],
		'attribution.primary_donor.address.postal_code' => [ 'status' => self::STATUS_USED ],
		'attribution.primary_donor.address.country' => [ 'status' => self::STATUS_USED ],
		'attribution.joint_donor' => [],
		'attribution.joint_donor.email' => [],
		'attribution.joint_donor.full_name' => [],
		'attribution.primary_donor.phone' => [
			'sample' => '123-456-789',
			'status' => self::STATUS_USED,
			'note' => 'seen in some cybersource, phone number not also set',
		],
		'donor_advised_fund_grant' => [],
		'donor_advised_fund_grant.donor_fund_name' => [ 'status' => self::STATUS_USED ],
		'donor_advised_fund_grant.organization_name' => [ 'status' => self::STATUS_USED ],
		'donor_advised_fund_grant.program_name' => [],
		'donor_advised_fund_grant.sponsor_grant_id' => [],
		'platform.acceptance' => [],
		'platform.acceptance.accepted' => [],
		'platform.acceptance.expires_at' => [],
		'platform.name' => [ 'status' => self::STATUS_USED ],
		'platform.platform_grant_id' => [],
		'platform.metadata.contributionId' => [],
		'platform.metadata.donorId' => [],
		'platform.metadata.nonprofitId' => [],
		'platform.metadata.Payable To' => [],
		'platform.metadata.Recommended By' => [],
		'platform.metadata.Description' => [ 'status' => self::STATUS_USED ],
		'platform.metadata.Activity' => [],
		'platform.metadata.Disbursement ID' => [],
		'platform.metadata.Disbursing Entity' => [],
		'platform.metadata.Fee Comment' => [],
		'platform.metadata.Frequency' => [],
		'platform.metadata.Project' => [],
		'platform.metadata.Project Remote ID' => [],
		'platform.metadata.Reason' => [],
		'platform.metadata.Acknowledgement' => [ 'status' => self::STATUS_USED ],
		'platform.metadata.Confirmation Number' => [],
		'platform.metadata.Disbursement Method' => [],
		'platform.metadata.Distribution' => [],
		'platform.metadata.Donation Frequency' => [
			'status' => self::STATUS_IGNORED,
			'sample' => 'one_time',
			'note' => 'seen for cybersource. Maybe use in future',
		],
		'platform.metadata.Payment Date' => [
			'status' => self::STATUS_IGNORED,
			'sample' => '2026-07-07',
			'note' => 'seen for cybersource, but not other platforms - we are using the settled date at the moment',
		],
		'platform.metadata.Payment Method' => [
			'status' => self::STATUS_IGNORED,
			'sample' => 'ACH',
			'note' => 'seen for cybersource. Is the same as we are determining from the deposit',
		],
		'platform.metadata.Payment Number' => [
			'status' => self::STATUS_IGNORED,
			'sample' => 'ACH',
			'note' => 'seen for cybersource. I Let chariot know that it looked wrong - ie ACH rather than a number',
		],
		'platform.metadata.Foreign Exchange Rate' => [
			'status' => self::STATUS_USED,
			'note' => 'We calculate this on the deposit level but if it is present on donation level maybe it varies? '
			. ' Shows up with Benevity',
		],
		'platform.metadata.Donor ID' => [
			'sample' => '9465027',
			'status' => self::STATUS_IGNORED,
			'note' => 'From YourCause platform',
		],
		'platform.metadata.Donor Type' => [
			'sample' => 'Individual',
			'status' => self::STATUS_IGNORED,
			'note' => 'From YourCause platform',
		],
		'platform.metadata.Payment ID' => [
			'sample' => '9700368688',
			'status' => self::STATUS_IGNORED,
			'note' => 'From YourCause platform',
		],
		'platform.metadata.Transaction Type' => [
			'sample' => 'Individual Payroll',
			'status' => self::STATUS_IGNORED,
			'note' => 'From YourCause platform',
		],
		'platform.metadata.Project External ID' => [
			'note' => 'Benevity - unknown reference',
			'sample' => '216435446f434a6c72',
			'status' => self::STATUS_IGNORED,
		],
		'platform.metadata.Community Investment Grant Requirements' => [
			'note' => 'Benevity - unknown reference, empty so far',
			'status' => self::STATUS_IGNORED,
		],
		'platform.metadata.Alternate Recognition Name' => [
			'sample' => 'Jane Doe',
			'note' => 'YourCause field - same as in the full_name field',
			'status' => self::STATUS_IGNORED,
		],
		'platform.metadata.Pass-through Agent' => [
			'sample' => 'Giving Charity',
			'note' => 'CyberGrant field - same as in the corporate_match.program_name field',
			'status' => self::STATUS_IGNORED,
		],
		'properties.Campaign' => [],
		'properties.Appeal Code' => [
			'status' => self::STATUS_USED,
			'sample' => 'White Mail',
			'note' => 'Maps to our Gift_Data.Appeal (output as direct_mail_appeal)'
		],
		'properties.Country' => [ 'status' => self::STATUS_USED ],
		'properties.Partner' => [ 'status' => self::STATUS_USED ],
		'properties.Prefix' => [ 'status' => self::STATUS_USED ],
		'properties.Suffix' => [ 'status' => self::STATUS_USED ],
		'properties.Review status' => [],
		'properties.Journaled in Sage' => [],
		'properties.Groundswell Company Name' => [],
		'properties.Marked for export' => [],
		'properties.Endowment flag?' => [ 'status' => self::STATUS_USED ],
		'properties.CRM status' => [],
		'properties.Check Number' => [
			'status' => self::STATUS_USED,
			'note' => 'alternate location for check_number (also on deposit in some cases). Set by user defined policy',
		],
		'properties.Gift Type' => [
			'status' => self::STATUS_USED,
			'note' => 'Used to categorise Groundswell donations.',
		],
		'settlement.deposit_id' => [],
		'settlement.received_at' => [],
		'settlement.settled_at' => [],
		'donor_email' => [ 'status' => self::STATUS_USED ],
		'donor_phone' => [ 'status' => self::STATUS_USED ],
		'assignee' => [],
		'crm_status' => [],
		'groundswell_company_name' => [],
		'internal_note' => [],
		'partner' => [ 'status' => self::STATUS_USED ],
		'partner_full_name' => [ 'status' => self::STATUS_USED ],
		'prefix' => [ 'status' => self::STATUS_USED ],
		'suffix' => [],
		'received_offline_on' => [],
		'review_status' => [],
		'dafpay_form' => [],
		'dafpay_frequency' => [ 'status' => self::STATUS_USED ],
		'dafpay_tracking_id' => [ 'status' => self::STATUS_USED ],
		'dafpay_type' => [ 'status' => self::STATUS_USED ],
		'dafpay_url' => [ 'status' => self::STATUS_USED ],
		'initiation.web_location_url' => [
			'status' => self::STATUS_USED,
			'sample' => "https://wikimediafoundation.org/give/donor-advised-funds/",
			'note' => 'Alternate to dafpay_url, requesting chariot normalise, appears for DAFFY',
		],
		'initiation.frequency' => [
			'status' => self::STATUS_USED,
			'sample' => 'ONE_TIME',
			'note' => 'Alternate to dafpay_frequency, requesting chariot normalise, appears for DAFFY',
		],
		'initiation.dafpay_tracking_id' => [
			'status' => self::STATUS_USED,
			'sample' => 'INTEGRATED',
			'note' => 'Alternate to dafpay_type, requesting chariot normalise, appears for DAFFY',
		],
		'initiation.channel' => [
			'status' => self::STATUS_USED,
			'sample' => 'ZJXUH21234',
			'note' => 'Alternate to dafpay_tracking_id, requesting chariot normalise, appears for DAFFY',
		],
		'initiation.initiated_at' => [
			'status' => self::STATUS_IGNORED,
			'sample' => "2026-06-18T14:56:03.216364Z",
			'note' => 'DAFPay started at, appears for DAFFY',
		],
		'initiation.dafpay_form' => [
			'status' => self::STATUS_IGNORED,
			'sample' => 'Components',
			'note' => 'Dafpay related, appears for DAFFY',
		],
		'corporate_match.company_name' => [ 'status' => self::STATUS_USED ],
		'corporate_match.match_amount' => [ 'status' => self::STATUS_USED ],
		'corporate_match.program_name' => [],
		'corporate_match.source' => [ 'status' => self::STATUS_USED ],
		'lockbox_id' => [
			'note' => 'Digital Mailbox field.',
		],
		'mail_item_id' => [
			'note' => 'Digital Mailbox field.',
		],
	];

	public static function getDepositFields(): array {
		return self::DEPOSIT_FIELDS;
	}

	public static function getDonationFields(): array {
		return self::DONATION_FIELDS;
	}

	public static function getKnownDepositPaths(): array {
		return self::getKnownPaths( self::DEPOSIT_FIELDS );
	}

	public static function getKnownDonationPaths(): array {
		return self::getKnownPaths( self::DONATION_FIELDS );
	}

	/**
	 * @param array<string,array<string,mixed>> $fields
	 * @return array<int,string>
	 */
	private static function getKnownPaths( array $fields ): array {
		$paths = [];

		foreach ( array_keys( $fields ) as $path ) {
			$parts = explode( '.', $path );
			$currentPath = '';

			foreach ( $parts as $part ) {
				$currentPath = $currentPath === '' ? $part : $currentPath . '.' . $part;
				$paths[$currentPath] = true;
			}
		}

		return array_keys( $paths );
	}

	public static function assertDepositFieldIsUsed( string $path ): void {
		self::assertFieldIsUsed( $path, self::DEPOSIT_FIELDS, 'deposit' );
	}

	public static function assertDonationFieldIsUsed( string $path ): void {
		self::assertFieldIsUsed( $path, self::DONATION_FIELDS, 'donation' );
	}

	private static function assertFieldIsUsed( string $path, array $fields, string $objectType ): void {
		if ( ( $fields[$path]['status'] ?? null ) !== self::STATUS_USED ) {
			throw new \RuntimeException(
				sprintf(
					'Chariot %s field "%s" is used by code but is not marked used in metadata.',
					$objectType,
					$path
				)
			);
		}
	}

}
