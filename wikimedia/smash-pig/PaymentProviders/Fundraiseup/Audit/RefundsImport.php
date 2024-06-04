<?php

namespace SmashPig\PaymentProviders\Fundraiseup\Audit;

use SmashPig\Core\DataFiles\DataFileException;
use SmashPig\Core\DataFiles\HeadedCsvReader;

class RefundsImport extends FundraiseupImports {
	protected array $importMap = [
		'Account Name' => 'account',
		'Account ID' => 'gateway_account',
		'Payment ID' => 'gateway_parent_id',
		'Refunded Platform Fee' => 'fee',
		'Converted Refund Amount (USD)' => 'refund',
		'Refund Amount' => 'gross',
		'Refund Amount Currency' => 'gross_currency',
		'Refund Date' => 'date'
	];

	public static function isMatch( string $filename ): bool {
		return preg_match( '/.*export_refunds_.*csv/', $filename );
	}

	/**
	 * @param HeadedCsvReader $csv
	 * @return array
	 * @throws DataFileException
	 */
	protected function parseLine( HeadedCsvReader $csv ): array {
		if ( $csv->currentCol( 'Donation Status' ) == 'refunded' ) {
			$msg = parent::parseLine( $csv );
			$msg['gateway_refund_id'] = $msg['gateway_parent_id'];
			$msg['type'] = 'refund';
			return $msg;
		}
		return [];
	}
}
