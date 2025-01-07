<?php

namespace SmashPig\PaymentProviders\Fundraiseup\Audit;

use SmashPig\Core\DataFiles\DataFileException;
use SmashPig\Core\DataFiles\HeadedCsvReader;

class DonationsImport extends FundraiseupImports {
	protected array $importMap = [
		'Account Name' => 'gateway_account',
		'Donation ID' => 'order_id',
		'Receipt ID' => 'invoice_id',
		'Donation Frequency' => 'frequency_unit',
		'Donation Currency' => 'original_currency',
		'Donation Amount' => 'original_gross',
		'Donation Payout Currency' => 'currency',
		'Converted Donation Amount (USD)' => 'gross',
		'Payment Processing Fee' => 'fee',
		'Donation Date' => 'date',
		'Is Recurring Donation' => 'recurring',
		'Recurring ID' => 'subscr_id',
		'Recurring Began' => 'start_date',
		'Payment ID' => 'gateway_txn_id',
		'Payment Method' => 'payment_method',
		'Credit Card Type' => 'payment_submethod',
		'Supporter First Name' => 'first_name',
		'Supporter Last Name' => 'last_name',
		'Supporter Employer' => 'employer',
		'Supporter Email' => 'email',
		'Mailing Address Line 1' => 'street_address',
		'Mailing Address Line 2' => 'street_number',
		'Mailing City' => 'city',
		'Mailing Zip/Postal' => 'postal_code',
		'Mailing State/Region' => 'state_province',
		'Mailing Country Code' => 'country',
		'Supporter Language' => 'language',
		'Supporter IP Address' => 'user_ip',
		'Supporter ID' => 'external_identifier',
		'UTM Medium' => 'utm_medium',
		'UTM Source' => 'utm_source',
		'UTM Campaign' => 'utm_campaign',
	];

	public static function isMatch( $filename ): bool {
		return preg_match( '/.*export_donations_.*csv/', $filename );
	}

	/**
	 * @param HeadedCsvReader $csv
	 * @return array
	 * @throws DataFileException
	 */
	protected function parseLine( HeadedCsvReader $csv ): array {
		// Only allow successful donations in,
		// for donations refunded on the same day as the donation,
		// FRUP sets the status to refunded in the exports
		$allowedDonationStatus = [ 'success', 'refunded', 'succeeded' ];
		$status = $csv->currentCol( 'Donation Status - New' );
		if ( in_array( $status, $allowedDonationStatus ) ) {
			$msg = parent::parseLine( $csv );
			$msg['type'] = 'donations';
			if ( empty( $msg['email'] ) ) {
				$paypalEmail = $csv->currentCol( 'Paypal Email' );
				if ( !empty( $paypalEmail ) ) {
					$msg['email'] = $paypalEmail;
				}
			}
			$msg['fee'] += floatval( $csv->currentCol( 'Donation Platform Fee' ) );

			if ( empty( $msg['country'] ) ) {
				$donationURL = $csv->currentCol( 'Donation Page URL' );
				$msg['country'] = $this->getCountryFromDonationURL( $donationURL );
			}
			if ( empty( $msg['utm_medium'] ) ) {
				$utm_medium = $csv->currentCol( 'WMF Medium' );
				$msg['utm_medium'] = $utm_medium ?? '';
			}
			if ( empty( $msg['utm_campaign'] ) ) {
				$utm_campaign = $csv->currentCol( 'WMF Campaign' );
				$msg['utm_campaign'] = $utm_campaign ?? '';
			}
			if ( empty( $msg['utm_source'] ) ) {
				$utm_source = $csv->currentCol( 'WMF Source' );
				$msg['utm_source'] = $utm_source ?? '';
			}
			return $msg;
		}
		return [];
	}

}
