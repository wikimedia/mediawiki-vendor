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
 * Processes Adyen's Settlement Detail Reports.
 * Sends donations, chargebacks, and refunds to queue.
 * https://docs.adyen.com/manuals/reporting-manual/settlement-detail-report-structure/settlement-detail-report-journal-types
 */
class AdyenAudit implements AuditParser {

	protected $columnHeaders = [
		'Company Account',
		'Merchant Account',
		'Psp Reference',
		'Merchant Reference',
		'Payment Method',
		'Creation Date',
		'TimeZone',
		'Type',
		'Modification Reference',
		'Gross Currency',
		'Gross Debit (GC)',
		'Gross Credit (GC)',
		'Exchange Rate',
		'Net Currency',
		'Net Debit (NC)',
		'Net Credit (NC)',
		'Commission (NC)',
		'Markup (NC)',
		'Scheme Fees (NC)',
		'Interchange (NC)',
		'Payment Method Variant',
		'Modification Merchant Reference',
		'Batch Number',
		'Reserved4',
		'Reserved5',
		'Reserved6',
		'Reserved7',
		'Reserved8',
		'Reserved9',
		'Reserved10',
	];

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
	];

	protected $fileData;

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
		$type = strtolower( $row['Type'] );
		if ( in_array( $type, self::$ignoredTypes ) ) {
			return;
		}

		$msg = [
			'gateway' => 'adyen',
			'invoice_id' => $row['Merchant Reference'],
			'date' => $this->getDate( $row ),
		];
		$parts = explode( '.', $row['Merchant Reference'] );
		$msg['contribution_tracking_id'] = $parts[0];

		switch ( $type ) {
			case 'settled':
				$this->parseDonation( $row, $msg );
				break;
			case 'chargeback':
			case 'refunded':
				$this->parseRefund( $row, $msg );
				break;
			default:
				throw new OutOfBoundsException( "Unknown audit line type {$row['Type']}." );
		}

		$this->fileData[] = $msg;
	}

	protected function parseRefund( array $row, array &$msg ) {
		$msg['gross'] = $row['Gross Debit (GC)']; // Actually paid to donor
		$msg['gross_currency'] = $row['Gross Currency'];
		// 'Net Debit (NC)' is the amount we paid including fees
		// 'Net Currency' is the currency we paid in
		// Deal with these when queue consumer can understand them

		$msg['gateway_parent_id'] = $row['Psp Reference'];
		$msg['gateway_refund_id'] = $row['Modification Reference'];
		if ( strtolower( $row['Type'] ) === 'chargeback' ) {
			$msg['type'] = 'chargeback';
		} else {
			$msg['type'] = 'refund';
		}
	}

	protected function parseDonation( array $row, array &$msg ) {
		$msg['gateway_txn_id'] = $row['Psp Reference'];
		// T306944
		// We were saving the Capture ID for 2+ recurrings in civi which for settled payments is in the
		// Modification reference. Adding this lets us match donations until the data is cleaned up
		$msg['modification_reference'] = $row['Modification Reference'];

		$msg['currency'] = $row['Gross Currency'];
		$msg['gross'] = $row['Gross Credit (GC)'];
		// fee is given in settlement currency
		// but queue consumer expects it in original
		$exchange = $row['Exchange Rate'];
		$fee = floatval( $row['Commission (NC)'] ) +
			floatval( $row['Markup (NC)'] ) +
			floatval( $row['Scheme Fees (NC)'] ) +
			floatval( $row['Interchange (NC)'] );
		$msg['fee'] = round( $fee / $exchange, 2 );

		// shouldn't this be settled_net or settled_amount?
		$msg['settled_gross'] = $row['Net Credit (NC)'];
		$msg['settled_currency'] = $row['Net Currency'];
		$msg['settled_fee'] = $fee;

		list( $method, $submethod ) = ReferenceData::decodePaymentMethod(
			$row['Payment Method'],
			$row['Payment Method Variant']
		);
		$msg['payment_method'] = $method;
		$msg['payment_submethod'] = $submethod;
	}

	protected function getDate( $row ) {
		$local = $row['Creation Date'];
		$zone = $row['TimeZone'];
		return UtcDate::getUtcTimestamp( $local, $zone );
	}
}
