<?php namespace SmashPig\PaymentProviders\Adyen\Audit;

use OutOfBoundsException;
use SmashPig\Core\DataFiles\AuditParser;
use SmashPig\Core\Logging\Logger;
use SmashPig\Core\NormalizationException;
use SmashPig\Core\UtcDate;
use SmashPig\PaymentProviders\Adyen\AdyenCurrencyRoundingHelper;
use SmashPig\PaymentProviders\Adyen\ReferenceData;

/**
 * Class AdyenAudit
 * @package SmashPig\PaymentProviders\Adyen\Audit
 * Processes Adyen's Settlement Detail and Payments Accounting Reports.
 * Sends donations, chargebacks, and refunds to queue.
 * https://docs.adyen.com/manuals/reporting-manual/settlement-detail-report-structure/settlement-detail-report-journal-types
 * https://docs.adyen.com/reporting/invoice-reconciliation/payment-accounting-report
 */
abstract class AdyenAudit implements AuditParser {

	protected static $ignoredTypes = [
		'misccosts',
		'merchantpayout',
		'depositcorrection',
		'matchedstatement',
		'manualcorrected',
		'authorisationschemefee',
		'bankinstructionreturned',
		'internalcompanypayout',
		'epapaid',
		'balancetransfer',
		'paymentcost',
		'settlecost',
		'paidout',
		'paidoutreversed',
		'reserveadjustment',
		// payments accounting report ignored types
		'received',
		'authorised',
		'sentforsettle',
		'refused',
		'retried',
		'reversed',
		'cancelled',
		'sentforrefund',
		'expired',
		'error',
		'capturefailed',
		'refundfailed'
	];

	protected $fileData;
	/** @var string the state of the payment in the report */
	protected $type;
	/** @var string the date of the state of the payment for example when it settled or was refunded */
	protected $date;
	protected $merchantReference = 'Merchant Reference';
	protected $columnHeaders;

	/**
	 * @var array
	 * These are the common ones - subclasses should add more in their constructors.
	 */
	protected $requiredColumns = [
		'Merchant Account',
		'Merchant Reference',
		'Psp Reference',
		'Payment Method',
		'Payment Method Variant',
		'TimeZone',
	];

	abstract protected function parseDonation( array $row, array $msg );

	abstract protected function parseRefund( array $row, array $msg );

	public function parseFile( string $path ): array {
		$this->fileData = [];
		$file = fopen( $path, 'r' );

		$this->columnHeaders = fgetcsv( $file, 0 );

		$missingColumns = [];
		foreach ( $this->requiredColumns as $requiredColumn ) {
			if ( !in_array( $requiredColumn, $this->columnHeaders ) ) {
				$missingColumns[] = $requiredColumn;
			}
		}
		if ( count( $missingColumns ) > 0 ) {
			throw new \RuntimeException( 'Missing columns ' . implode( ',', $missingColumns ) );
		}

		while ( $line = fgetcsv( $file, 0 ) ) {
			try {
				$this->parseLine( $line );
			} catch ( NormalizationException $ex ) {
				// TODO: actually throw these below
				Logger::error( $ex->getMessage() );
			}
		}
		fclose( $file );

		return $this->fileData;
	}

	protected function parseLine( $line ) {
		$row = array_combine( $this->columnHeaders, $line );
		$type = strtolower( $row[$this->type] );
		if ( $type === 'fee' || $type === 'invoicededuction' ) {
			$this->fileData[] = $this->getFeeTransaction( $row );
			return;
		}
		if ( $type === 'merchantpayout' ) {
			$this->fileData[] = $this->getPayoutTransaction( $row );
			return;
		}
		if ( in_array( $type, self::$ignoredTypes ) ) {
			return;
		}

		$msg = $this->setCommonValues( $row );

		switch ( $type ) {
			case 'chargebackreversed':
			case 'refundedreversed':
				// Set the type and then treat as normal donation.
				if ( $type === 'chargebackreversed' ) {
					$msg['type'] = 'chargeback_reversed';
				}
				if ( $type === 'refundedreversed' ) {
					$msg['type'] = 'refund_reversed';
				}
				// fall through
			case 'settled':
				// Amex has externally in the type name
			case 'settledexternally':
				$msg = $this->parseDonation( $row, $msg );
				break;
			case 'chargeback':
			case 'chargebackexternally':
			case 'refunded':
			case 'refundedexternally':
			case 'secondchargeback':
				$msg = $this->parseRefund( $row, $msg );
				break;
			default:
				throw new OutOfBoundsException( "Unknown audit line type {$type}." );
		}

		$this->fileData[] = $msg;
	}

	public function getOrchestratorMetadata( $row ): array {
		return json_decode( $row['Metadata'] ?? '{}', true ) ?? [];
	}

	protected function parseCommonRefundValues( array $row, array $msg, string $messageType, string $modificationReference ): array {
		if ( in_array( strtolower( $messageType ), [ 'chargeback', 'secondchargeback' ] ) ) {
			$msg['type'] = 'chargeback';
		} else {
			$msg['type'] = 'refund';
		}

		if ( $this->isOrchestratorMerchantReference( $row ) ) {
			$msg['backend_processor_parent_id'] = $row['Psp Reference'];
			$msg['backend_processor_refund_id'] = $modificationReference;
		} else {
			$msg['gateway_parent_id'] = $row['Psp Reference'];
			$msg['gateway_refund_id'] = $modificationReference;
		}

		// This is REALLY confusing - but in the Adyen Settlement csv
		// we can find (e.g) a USD row like
		// Net Debit (NC)   = 15.65 (settled_total_amount)
		// Markup (NC)      = 10.65 (settled_fee_amount)
		// Gross Debit (GC) = 5     (settled_net_amount)
		// In this case we hit a chargeback where $5 USD was returned to the donor
		// We were charged $10.65 as a charge back penantly and
		// $15.65 is charged to us in total. If it were not USD Gross Debit (GC) would be
		// in the original currency.
		$msg['settled_fee_amount'] = AdyenCurrencyRoundingHelper::round( -$this->getFee( $row ), $msg['settled_currency'] );
		$msg['settled_total_amount'] = AdyenCurrencyRoundingHelper::round( $msg['settled_net_amount'] - $msg['settled_fee_amount'], $msg['settled_currency'] );
		$msg['fee'] = $msg['settled_fee_amount'] ? AdyenCurrencyRoundingHelper::round( $msg['settled_fee_amount'] / $msg['exchange_rate'], $msg['settled_currency'] ) : 0;
		$msg['original_total_amount'] = AdyenCurrencyRoundingHelper::round( -( (float)$msg['gross'] ), $msg['original_currency'] );
		$msg['original_fee_amount'] = AdyenCurrencyRoundingHelper::round( $msg['fee'], $msg['original_currency'] );
		$msg['original_net_amount'] = AdyenCurrencyRoundingHelper::round( $msg['original_total_amount'] + $msg['original_fee_amount'], $msg['original_currency'] );
		return $msg;
	}

	public function getGravyGatewayTransactionId( array $row ): ?string {
		$reference = $row['Merchant Reference'];
		if ( str_contains( $reference, '.' ) ) {
			// This would be unexpected for gravy transactions....
			return null;
		}
		return \SmashPig\Core\Helpers\Base62Helper::toUuid( $reference );
	}

	protected function getGatewayTransactionId( array $row ): ?string {
		if ( $this->isOrchestratorMerchantReference( $row ) ) {
			return $this->getGravyGatewayTransactionId( $row );
		} else {
			return $row['Psp Reference'];
		}
	}

	protected function getInvoiceId( array $row ): string {
		if ( $this->isOrchestratorMerchantReference( $row ) ) {
			$metadata = $this->getOrchestratorMetadata( $row );
			if ( isset( $metadata['gr4vy_tx_ref'] ) ) {
				return $metadata['gr4vy_tx_ref'];
			}
		}
		return $row['Merchant Reference'];
	}

	protected function getContributionTrackingId( array $row ): ?int {
		$invoiceId = $this->getInvoiceId( $row );
		if ( ( !strpos( $invoiceId, '.' ) && !is_numeric( $invoiceId ) ) ) {
			return null;
		}
		$parts = explode( '.', $invoiceId );
		return ( !empty( $parts[ 0 ] ) && is_numeric( $parts[ 0 ] ) ) ? (int)$parts[ 0 ] : null;
	}

	/**
	 * these column names are shared between SettlementDetail and PaymentsAccounting reports
	 */
	protected function setCommonValues( array $row ) {
		$msg = [
			'gateway' => $this->isOrchestratorMerchantReference( $row ) ? 'gravy' : 'adyen',
			'audit_file_gateway' => 'adyen',
			'gateway_account' => $row['Merchant Account'],
			'invoice_id' => $this->getInvoiceId( $row ),
			'gateway_txn_id' => $this->getGatewayTransactionId( $row ),
			'settlement_batch_reference' => $row['Batch Number'] ?? $row['Payable Batch'] ?? null,
			'exchange_rate' => $row['Exchange Rate']
		];

		$msg['settled_date'] = empty( $row['Booking Date'] ) ? null : UtcDate::getUtcTimestamp( $row['Booking Date'], $row['Booking Date TimeZone'] ?? $row['TimeZone'] );

		$msg['contribution_tracking_id'] = $this->getContributionTrackingId( $row );

		[ $method, $submethod ] = ReferenceData::decodePaymentMethod(
			$row['Payment Method'],
			$row['Payment Method Variant']
		);
		if ( $this->getEmail( $row ) ) {
			$msg['email'] = $this->getEmail( $row );
		}

		$msg['payment_method'] = $method;
		$msg['payment_submethod'] = $submethod;
		// Both reports have the Creation Date in PDT, the payments accounting report does not
		// send the timezone as a separate column
		$msg['date'] = UtcDate::getUtcTimestamp( $row[$this->date], $row['TimeZone'] );

		if ( $this->isOrchestratorMerchantReference( $row ) ) {
			$msg['backend_processor_txn_id'] = $row['Psp Reference'];
			$msg['backend_processor'] = 'adyen';
			$msg['payment_orchestrator_reconciliation_id'] = $row[$this->merchantReference];
		}

		return $msg;
	}

	/**
	 * Skip lines where we do not want to process
	 * eg Gravy transactions coming in on the adyen audit
	 *
	 */
	protected function isOrchestratorMerchantReference( array $row ): bool {
		$merchantReference = $row[$this->merchantReference];
		// ignore gravy transactions, they have no period and contain letters
		return ( !strpos( $merchantReference, '.' ) && !is_numeric( $merchantReference ) );
	}

	protected function getFeeTransaction( array $row ): ?array {
		return null;
	}

	protected function getEmail( array $row ): ?string {
		if ( $this->isOrchestratorMerchantReference( $row ) ) {
			$metadata = $this->getOrchestratorMetadata( $row );
			if ( isset( $metadata['gr4vy_buy_ref'] ) ) {
				return $metadata['gr4vy_buy_ref'] ?: null;
			}
		}
		return null;
	}
}
