<?php namespace SmashPig\PaymentProviders\AstroPay\Audit;

use OutOfBoundsException;
use SmashPig\Core\Context;
use SmashPig\Core\DataFiles\AuditParser;
use SmashPig\Core\Logging\Logger;
use SmashPig\Core\NormalizationException;
use SmashPig\Core\UtcDate;
use SmashPig\PaymentProviders\dlocal\ReferenceData;

class AstroPayAudit implements AuditParser {

	protected $columnHeaders = [
		'Type', // 'Payment' or 'Refund'
		'Creation date', // YYYY-MM-dd HH:mm:ss
		'Settlement date', // same format
		'Reference', // gateway_txn_id
		'Invoice', // ct_id.attempt_num
		'Country',
		'Payment Method', // corresponds to our payment_submethod
		'Payment Method Type', // our payment_method
		'Net Amount (local)',
		'Amount (USD)', // gross, including fee
		'currency', // yup, this one is lower case
		'Status',
		'User Mail',
		// These two fields refer to the original donation for refunds
		'Transaction Reference',
		'Transaction Invoice',
		'Fee', // In USD.  AstroPay's processing fee
		'IOF', // In USD.  Fee for financial transactions in Brazil
		// The IOF is included in AstroPay's fee, but broken out by request
	];

	protected $ignoredStatuses = [
		'Cancelled', // User pressed cancel or async payment expired
		'In process', // Chargeback is... charging back? 'Settled' means done
		'Reimbursed', // Chargeback settled in our favor - not refunding
		'Waiting Details', // Refund is in limbo; we'll wait for 'Completed'
	];

	protected $fileData;

	public function parseFile( string $path ): array {
		$this->fileData = [];
		$file = fopen( $path, 'r' );

		$ignoreLines = 1;
		for ( $i = 0; $i < $ignoreLines; $i++ ) {
			fgets( $file );
		}

		while ( $line = fgetcsv( $file, 0, ';', '"', '\\' ) ) {
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

		// Ignore certain statuses
		if ( in_array( $row['Status'], $this->ignoredStatuses ) ) {
			return;
		}

		$msg = [];

		// Common to all types
		$msg['date'] = UtcDate::getUtcTimestamp( $row['Creation date'] );
		$msg['gateway'] = $this->getGateway( $msg['date'] );
		$msg['gross'] = $row['Net Amount (local)'];

		switch ( $row['Type'] ) {
			case 'Payment':
				$this->parseDonation( $row, $msg );
				break;
			case 'Refund':
			case 'Chargeback':
			case 'Chargebacks': // started seeing these with the 's'
				$this->parseRefund( $row, $msg );
				break;
			case 'Credit Note':
			case 'Debit Note':
			case 'Chargeback Reversal':
			case 'Refund processing fee':
			case 'Chargeback processing fee':
				// TODO these would have to update existing refunds
				// If they show up in the same file as the associate refund or
				// chargeback, we could just update those rows before returning
				// the array of transactions.
				return;
			default:
				throw new OutOfBoundsException( "Unknown audit line type {$row['Type']}." );
		}

		$this->fileData[] = $msg;
	}

	protected function parseRefund( array $row, array &$msg ) {
		$msg['contribution_tracking_id'] = $this->getContributionTrackingId( $row['Transaction Invoice'] );
		$msg['gateway_parent_id'] = $row['Transaction Reference'];
		$msg['gateway_refund_id'] = $row['Reference'];
		$msg['gross_currency'] = $row['currency'];
		$msg['invoice_id'] = $row['Transaction Invoice'];
		$msg['type'] = strtolower( $row['Type'] );
		if ( $msg['type'] === 'chargebacks' ) {
			// deal with stray plural form, but don't break if they fix it
			$msg['type'] = 'chargeback';
		}
	}

	protected function parseDonation( array $row, array &$msg ) {
		$msg['contribution_tracking_id'] = $this->getContributionTrackingId( $row['Invoice'] );
		$msg['country'] = $row['Country'];
		$msg['currency'] = $row['currency'];
		$msg['email'] = $row['User Mail'];
		$msg['settled_fee'] = $row['Fee']; // settled_fee since it's given in USD
		$msg['gateway_txn_id'] = $row['Reference'];
		$msg['invoice_id'] = $row['Invoice'];
		list( $method, $submethod ) = ReferenceData::decodePaymentMethod(
			$row['Payment Method Type'],
			$row['Payment Method']
		);
		$msg['payment_method'] = $method;
		$msg['payment_submethod'] = $submethod;
		if ( $row['Settlement date'] ) {
			$msg['settled_date'] = UtcDate::getUtcTimestamp( $row['Settlement date'] );
		}
		if ( $row['Amount (USD)'] ) {
			$msg['settled_currency'] = 'USD';
			$msg['settled_gross'] = $row['Amount (USD)'];
		}
	}

	protected function getContributionTrackingId( $invoice ) {
		$parts = explode( '.', $invoice );
		return $parts[0];
	}

	protected function getGateway( int $timestamp ): string {
		static $cutoverDate;
		if ( $cutoverDate === null ) {
			$config = Context::get()->getProviderConfiguration();
			if ( $config->nodeExists( 'api-cutover-date' ) ) {
				$cutoverDate = UtcDate::getUtcTimestamp( $config->val( 'api-cutover-date' ) );
			} else {
				$cutoverDate = false;
			}
		}
		if ( $cutoverDate === false ) {
			return 'astropay';
		}
		return ( $timestamp > $cutoverDate ) ? 'dlocal' : 'astropay';
	}
}
