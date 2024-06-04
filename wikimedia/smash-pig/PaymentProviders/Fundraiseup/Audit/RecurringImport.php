<?php

namespace SmashPig\PaymentProviders\Fundraiseup\Audit;

use SmashPig\Core\DataFiles\DataFileException;
use SmashPig\Core\DataFiles\HeadedCsvReader;

class RecurringImport extends FundraiseupImports {
	protected array $importMap = [
		'Account Name' => 'gateway_account',
		'Recurring ID' => 'subscr_id',
		'Supporter First Name' => 'first_name',
		'Supporter Last Name' => 'last_name',
		'Supporter Employer' => 'employer',
		'Supporter Email' => 'email',
		'Supporter ID' => 'external_identifier',
		'Mailing Country Code' => 'country',
		'Recurring Amount' => 'gross',
		'Recurring Currency' => 'currency',
		'Next Donation Date' => 'next_sched_contribution_date',
		'Payment Method' => 'payment_method',
		'Credit Card Type' => 'payment_submethod',
		'UTM Medium' => 'utm_medium',
		'UTM Source' => 'utm_source',
		'UTM Campaign' => 'utm_campaign',
		'Recurring Began' => 'start_date',
		'Recurring Frequency' => 'frequency_unit'
	];

	public static function isMatch( string $filename ): bool {
		return (bool)preg_match( '/.*export_recurring_.*csv/', $filename );
	}

	/**
	 * @param HeadedCsvReader $csv
	 * @return array
	 * @throws DataFileException
	 */
	protected function parseLine( HeadedCsvReader $csv ): array {
		$msg = parent::parseLine( $csv );
		$msg['type'] = 'recurring';
		$msg['txn_type'] = 'subscr_signup';
		if ( $this->isCancelled( $csv ) || $this->isFailed( $csv ) ) {
			$msg['cancel_date'] = strtotime( $csv->currentCol( 'Cancelled Date' ) );
			$msg['date'] = $msg['cancel_date'];
			$msg['txn_type'] = 'subscr_cancel';
			if ( $this->isFailed( $csv ) ) {
				$msg['cancel_date'] = strtotime( $csv->currentCol( 'Failed Date' ) );
				$msg['cancel_reason'] = 'Failed: ' . $csv->currentCol( 'Latest Payment Error Message' );
			}
		}
		if ( !empty( $msg['next_sched_contribution_date'] ) ) {
			$msg['next_sched_contribution_date'] = strtotime( $msg['next_sched_contribution_date'] );
		}
		if ( !empty( $msg['start_date'] ) ) {
			$msg['start_date'] = strtotime( $msg['start_date'] );
			$msg['create_date'] = $msg['start_date'];
			if ( empty( $msg['date'] ) ) {
				$msg['date'] = $msg['create_date'];
			}
		}

		if ( empty( $msg['country'] ) ) {
			$donationURL = $csv->currentCol( 'Recurring Page URL' );
			$msg['country'] = $this->getCountryFromDonationURL( $donationURL );
		}
		return $msg;
	}

	/**
	 * @param HeadedCsvReader $csv
	 * @return bool
	 * @throws DataFileException
	 */
	protected function isCancelled( HeadedCsvReader $csv ): bool {
		return $csv->currentCol( 'Recurring Status' ) === 'cancelled';
	}

	/**
	 * @param HeadedCsvReader $csv
	 * @return bool
	 * @throws DataFileException
	 */
	protected function isFailed( HeadedCsvReader $csv ): bool {
		return $csv->currentCol( 'Recurring Status' ) === 'failed';
	}
}
