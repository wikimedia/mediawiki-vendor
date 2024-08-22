<?php

namespace SmashPig\PaymentProviders\Gravy\Audit;

use SmashPig\Core\DataFiles\AuditParser;
use SmashPig\Core\DataFiles\HeadedCsvReader;
use SmashPig\Core\Logging\Logger;
use SmashPig\Core\UtcDate;

class GravyAudit implements AuditParser {

	protected array $fieldMappings = [
			'order_id' => 'external_identifier',
			'currency' => 'currency',
			'date' => 'created_at',
			'gateway_txn_id' => 'id',
			'gross' => 'captured_amount',
			'invoice_id' => 'external_identifier',
			'payment_method' => 'scheme',
			'email' => 'billing_details_email_address',
			'first_name' => 'billing_details_first_name',
			'last_name' => 'billing_details_last_name',
			'settled_gross' => 'captured_amount',
			'settled_currency' => 'currency',
			'gross_currency' => 'currency'
// 'backend_processor' => 'payment_service_display_id',
	];

	public function parseFile( string $path ): array {
		$csv = new HeadedCsvReader( $path );
		$fileData = [];

		while ( $csv->valid() ) {
			try {
				$transactionType = $csv->currentCol( 'status' );
				if ( $transactionType === 'capture_succeeded' ) {
					$lineData = $this->parseLine( $csv );
					$fileData[] = $lineData;
				}
				$csv->next();
			} catch ( \Exception $ex ) {
				Logger::error( $ex->getMessage() );
			}
		}

		return $fileData;
	}

	protected function parseLine( HeadedCsvReader $csv ): array {
		$normalizedLineData['gateway'] = 'gravy';
		foreach ( $this->fieldMappings as $localFieldName => $gravyFieldName ) {
			$normalizedLineData[$localFieldName] = $csv->currentCol( $gravyFieldName );
		}

		// pull ct_id from order_id
		if ( isset( $normalizedLineData['order_id'] ) ) {
			$orderId = $normalizedLineData['order_id'];
			$ctId = explode( '.', $orderId )[0];
			$normalizedLineData['contribution_tracking_id'] = $ctId;
		}

		// convert date string to timestamp
		if ( isset( $normalizedLineData['date'] ) ) {
			$date = $normalizedLineData['date'];
			$timestamp = UtcDate::getUtcTimestamp( $date );
			$normalizedLineData['date'] = $timestamp;
		}

		// convert amount from cents(minor units) to major units
		// TODO: when we add the next processor, this will have to change!
		if ( isset( $normalizedLineData['settled_gross'] ) ) {
			$normalizedLineData['settled_gross'] /= 100;
		}

		if ( isset( $normalizedLineData['gross'] ) ) {
			$normalizedLineData['gross'] /= 100;
		}

		return $normalizedLineData;
	}
}
