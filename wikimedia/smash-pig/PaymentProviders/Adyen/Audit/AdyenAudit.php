<?php namespace SmashPig\PaymentProviders\Adyen\Audit;

use OutOfBoundsException;
use SmashPig\Core\DataFiles\AuditParser;
use SmashPig\Core\Logging\Logger;
use SmashPig\Core\NormalizationException;
use SmashPig\Core\UtcDate;
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
		'fee',
		'misccosts',
		'merchantpayout',
		'chargebackreversed', // oh hey, we could try to handle these
		'refundedreversed',
		'depositcorrection',
		'invoicededuction',
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
		'capturefailed'
	];

	protected $fileData;
	// the state of the payment in the report
	protected $type;
	// the date of the state of the payment for example when it settled or was refunded
	protected $date;
	protected $columnHeaders;

	abstract protected function parseDonation( array $row, array $msg );

	abstract protected function parseRefund( array $row, array $msg );

	public function parseFile( string $path ): array {
		$this->fileData = [];
		$file = fopen( $path, 'r' );

		$ignoreLines = 1;
		for ( $i = 0; $i < $ignoreLines; $i++ ) {
			fgets( $file );
		}

		while ( $line = fgetcsv( $file, 0, ',', '"', '\\' ) ) {
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
		if ( in_array( $type, self::$ignoredTypes ) ) {
			return;
		}

		// maybe just put this back then
		$msg = $this->setCommonValues( $row );

		switch ( $type ) {
			// Amex has externally in the type name
			case 'settled':
			case 'settledexternally':
				$msg = $this->parseDonation( $row, $msg );
				break;
			case 'chargeback':
			case 'chargebackexternally':
			case 'refunded':
			case 'refundedexternally':
				$msg = $this->parseRefund( $row, $msg );
				break;
			default:
				throw new OutOfBoundsException( "Unknown audit line type {$type}." );
		}

		$this->fileData[] = $msg;
	}

	// these column names are shared between SettlementDetail and PaymentsAccounting reports
	protected function setCommonValues( $row ) {
		$msg = [
			'gateway' => 'adyen',
			'gateway_account' => $row['Merchant Account'],
			'invoice_id' => $row['Merchant Reference'],
			'gateway_txn_id' => $row['Psp Reference']
		];
		$parts = explode( '.', $row['Merchant Reference'] );
		$msg['contribution_tracking_id'] = $parts[0];

		list( $method, $submethod ) = ReferenceData::decodePaymentMethod(
			$row['Payment Method'],
			$row['Payment Method Variant']
		);
		$msg['payment_method'] = $method;
		$msg['payment_submethod'] = $submethod;
		// Both reports have the Creation Date in PDT, the payments accounting report does not
		// send the timezone as a separate column
		$msg['date'] = UtcDate::getUtcTimestamp( $row[$this->date], $row['TimeZone'] );

		return $msg;
	}

}
