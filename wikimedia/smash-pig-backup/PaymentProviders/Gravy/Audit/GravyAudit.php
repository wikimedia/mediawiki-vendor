<?php

namespace SmashPig\PaymentProviders\Gravy\Audit;

use SmashPig\Core\DataFiles\AuditParser;
use SmashPig\Core\DataFiles\HeadedCsvReader;
use SmashPig\Core\Logging\Logger;
use SmashPig\Core\UtcDate;
use SmashPig\PaymentProviders\Gravy\GravyHelper;

class GravyAudit implements AuditParser {

	public const TRANSACTION_COMPLETED_STATUS = 'capture_succeeded';
	public const TRANSACTION_TYPE_REFUND = 'refund';

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
			'gross_currency' => 'currency',
			'payment_service_definition_id' => 'payment_service_definition_id',
	];

	public function parseFile( string $path ): array {
		$csv = new HeadedCsvReader( $path );
		$transactions = [];

		while ( $csv->valid() ) {
			try {
				$transactionType = $csv->currentCol( 'status' );
				if ( $transactionType === self::TRANSACTION_COMPLETED_STATUS ) {
					$row = $this->parseLine( $csv );
					if ( $this->rowIncludesRefund( $row ) ) {
						[ $payment, $refund ] = $this->extractPaymentAndRefundFromRow( $row );
						$transactions[] = $payment;
						$transactions[] = $refund;
					} else {
						$transactions[] = $row;

					}
				}
				$csv->next();
			} catch ( \Exception $ex ) {
				Logger::error( $ex->getMessage() );
			}
		}

		return $transactions;
	}

	protected function parseLine( HeadedCsvReader $csv ): array {
		$normalizedLineData['gateway'] = 'gravy';
		foreach ( $this->fieldMappings as $localFieldName => $gravyFieldName ) {
			$normalizedLineData[$localFieldName] = $csv->currentCol( $gravyFieldName );
		}

		// Extract ct_id from order_id
		$normalizedLineData['contribution_tracking_id'] = $this->getContributionTrackingId( $normalizedLineData );

		// Convert date string to timestamp
		$normalizedLineData['date'] = $this->getDateAsTimestamp( $normalizedLineData );

		// Convert amounts from cents to major units
		$normalizedLineData = $this->convertAmountFields( $normalizedLineData );

		// Extract payment processor from payment_service_definition_id
		$normalizedLineData = $this->addBackendProcessor( $normalizedLineData );

		// Check for refund and if found, add refund-specific fields
		$normalizedLineData = $this->addRefundFieldsIfRefundAmountSet( $csv, $normalizedLineData );

		return $normalizedLineData;
	}

  /**
   * Extract payment processor from payment_service_definition_id and return it
   *
   * @param array $data
   *
   * @return array
   */
	protected function addBackendProcessor( array $data ): array {
		if ( isset( $data['payment_service_definition_id'] ) ) {
			$data['backend_processor'] = GravyHelper::extractProcessorNameFromServiceDefinitionId( $data['payment_service_definition_id'] );
			unset( $data['payment_service_definition_id'] );
		}
		return $data;
	}

  /**
   * Pull ct_id from order_id and return it
   *
   * @param array $data
   *
   * @return string|null
   */
	protected function getContributionTrackingId( array $data ): ?string {
		if ( isset( $data['order_id'] ) ) {
			return explode( '.', $data['order_id'] )[0];
		}
		return null;
	}

  /**
   * Convert date string to timestamp
   *
   * @param array $data
   *
   * @return int|null
   */
	protected function getDateAsTimestamp( array $data ): ?int {
		if ( isset( $data['date'] ) ) {
			return UtcDate::getUtcTimestamp( $data['date'] );
		}
		return null;
	}

  /**
   * Convert amount from cents (minor units) to major units
   *
   * @param array $data
   *
   * @return array
   */
	protected function convertAmountFields( array $data ): array {
		$amountFields = [ 'settled_gross', 'gross' ];
		foreach ( $amountFields as $field ) {
			if ( isset( $data[$field] ) ) {
				$data[$field] /= 100;
			}
		}
		return $data;
	}

  /**
   * Check to see if row contains refund and add refund-specific fields if so
   *
   * @param \SmashPig\Core\DataFiles\HeadedCsvReader $csv
   * @param array $data
   *
   * @return array
   * @throws \SmashPig\Core\DataFiles\DataFileException
   */
	protected function addRefundFieldsIfRefundAmountSet( HeadedCsvReader $csv, array $data ): array {
		$refundedAmount = $csv->currentCol( 'refunded_amount' );
		if ( $refundedAmount !== null && $refundedAmount > 0 ) {
			$data['refunded_amount'] = $refundedAmount / 100;
			$data['refund_date'] = UtcDate::getUtcTimestamp( $csv->currentCol( 'updated_at' ) );
			$data['type'] = self::TRANSACTION_TYPE_REFUND;
		}
		return $data;
	}

	/**
	 * @param array $row
	 * @return bool
	 */
	protected function rowIncludesRefund( array $row ): bool {
		return array_key_exists( 'type', $row ) && $row['type'] === self::TRANSACTION_TYPE_REFUND;
	}

  /**
   * Gravy combine the payment and refund into a single row in the settlement
   * report, so we split them out and transform as needed when parsing the file.
   *
   * @param array $row
   *
   * @return array
   */
	protected function extractPaymentAndRefundFromRow( array $row ): array {
		// tidy up the payment row: remove refund info
		$payment = $row;
		unset( $payment['type'], $payment['refunded_amount'], $payment['refund_date'] );

		// tidy up the refund row: swap in refund amount and date and remove temp fields
		$refund = array_merge( $row, [
			'date' => $row['refund_date'],
			'gross' => $row['refunded_amount']
		] );
		unset( $refund['refunded_amount'], $refund['refund_date'] );

		return [ $payment, $refund ];
	}

}
