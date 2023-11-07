<?php

namespace SmashPig\PaymentProviders\Fundraiseup\Audit;

use SmashPig\Core\DataFiles\HeadedCsvReader;

class RecurringImport extends FundraiseupImports {
	protected $importMap = [
		'Account Name' => 'gateway_account',
		'Recurring ID' => 'subscr_id',
		'Payment ID' => 'gateway_txn_id',
		'Supporter First Name' => 'first_name',
		'Supporter Last Name' => 'last_name',
		'Supporter Employer' => 'employer',
		'Supporter Email' => 'email',
		'Supporter ID' => 'external_identifier',
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

	public static function isMatch( $filename ) {
		return preg_match( '/.*export_recurring_.*csv/', $filename );
	}

	/**
	 * @param HeadedCsvReader $csv
	 */
	protected function parseLine( HeadedCsvReader $csv ) {
		$msg = parent::parseLine( $csv );
		$msg['type'] = 'recurring';
		$msg['txn_type'] = 'subscr_signup';
		if ( $this->isCancelled( $csv ) ) {
   $msg['cancel_date'] = strtotime( $csv->currentCol( 'Cancelled Date' ) );
			$msg['date'] = $msg['cancel_date'];
			$msg['txn_type'] = 'subscr_cancel';
		}
		if ( !empty( $msg['next_sched_contribution_date'] ) ) {
			$msg['next_sched_contribution_date'] = strtotime( $msg['next_sched_contribution_date'] );
		}
		if ( !empty( $msg['start_date'] ) ) {
			$msg['start_date'] = strtotime( $msg['start_date'] );
			$msg['create_date'] = $msg['start_date'];
			$msg['date'] = $msg['create_date'];
		}
		return $msg;
	}

	/**
	 * @param HeadedCsvReader $csv
	 */
	protected function isCancelled( HeadedCsvReader $csv ) {
		return $csv->currentCol( 'Recurring Status' ) === 'cancelled';
	}
}
