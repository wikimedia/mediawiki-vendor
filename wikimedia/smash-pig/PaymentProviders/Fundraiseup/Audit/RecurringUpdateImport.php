<?php

namespace SmashPig\PaymentProviders\Fundraiseup\Audit;

use SmashPig\Core\DataFiles\DataFileException;
use SmashPig\Core\DataFiles\HeadedCsvReader;

class RecurringUpdateImport extends FundraiseupImports {

	protected array $importMap = [
		'Recurring ID' => 'subscr_id',
		'Supporter First Name' => 'first_name',
		'Supporter Last Name' => 'last_name',
		'Supporter Employer' => 'employer',
		'Supporter Email' => 'email',
		'Recurring Amount' => 'amount',
		'Payment method' => 'payment_method',
		'Credit Card Type' => 'payment_submethod',
		'Last Updated' => 'date',
		'Supporter ID' => 'external_identifier'
	];

	public static function isMatch( string $filename ): bool {
		return preg_match( '/.*export_recurring_plan_change.*csv/', $filename );
	}

	/**
	 * @param HeadedCsvReader $csv
	 * @return array
	 * @throws DataFileException
	 */
	protected function parseLine( HeadedCsvReader $csv ): array {
		$msg = parent::parseLine( $csv );
		$msg['type'] = 'recurring-modify';
		$msg['txn_type'] = 'external_recurring_modification';
		if ( $this->isCancelled( $csv ) || $this->isFailed( $csv ) ) {
			$msg['cancel_date'] = strtotime( $csv->currentCol( 'Cancelled Date' ) );
			$msg['date'] = $msg['cancel_date'];
			$msg['txn_type'] = 'subscr_cancel';
			if ( $this->isFailed( $csv ) ) {
				$msg['cancel_date'] = strtotime( $csv->currentCol( 'Failed Date' ) );
				$msg['cancel_reason'] = 'Failed: ' . $csv->currentCol( 'Latest Payment Error Message' );
			}
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
