<?php
declare( strict_types=1 );

namespace SmashPig\PaymentProviders\dlocal\Audit;

use OutOfBoundsException;
use SmashPig\Core\UtcDate;
use SmashPig\PaymentProviders\dlocal\ReferenceData;

/**
 * Parser class for the older report files.
 *
 * e.g wikimedia_report_2025-11-19.csv
 */
class ReportFileParser extends BaseParser {

	protected array $ignoredStatuses = [
		'Cancelled', // User pressed cancel or async payment expired
		'In process', // Chargeback is... charging back? 'Settled' means done
		'Reimbursed', // Chargeback settled in our favor - not refunding
		'Waiting Details', // Refund is in limbo; we'll wait for 'Completed'
	];

	public function parse(): ?array {
		// Ignore certain statuses
		if ( in_array( $this->row['Status'], $this->ignoredStatuses ) ) {
			return null;
		}
		$msg = [];
		// Common to all types
		$msg['date'] = UtcDate::getUtcTimestamp( $this->row['Creation date'] );
		$msg['gateway'] = 'dlocal';
		$msg['gross'] = $this->row['Net Amount (local)'];
		$msg['audit_file_gateway'] = 'dlocal';

		switch ( $this->row['Type'] ) {
			case 'Payment':
				if ( $this->isFromOrchestrator( $this->row['Invoice'] ) ) {
					return null;
				}
				$this->parseDonation( $msg );
				break;
			case 'Refund':
			case 'Chargeback':
			case 'Chargebacks': // started seeing these with the 's'
				if ( $this->isFromOrchestrator( $this->row['Transaction Invoice'] ) ) {
					return null;
				}
				$this->parseRefund( $msg );
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
				return null;
			default:
				throw new OutOfBoundsException( "Unknown audit line type {$this->row['Type']}." );
		}
		return $msg;
	}

	protected function parseRefund( array &$msg ): void {
		$msg['contribution_tracking_id'] = $this->getContributionTrackingId();
		$msg['gateway_parent_id'] = $this->row['Transaction Reference'];
		$msg['gateway_refund_id'] = $this->row['Reference'];
		$msg['gross_currency'] = $this->row['currency'];
		$msg['invoice_id'] = $this->row['Transaction Invoice'];
		$msg['type'] = strtolower( $this->row['Type'] );
		if ( $msg['type'] === 'chargebacks' ) {
			// deal with stray plural form, but don't break if they fix it
			$msg['type'] = 'chargeback';
		}
	}

	protected function parseDonation( array &$msg ): void {
		$msg['contribution_tracking_id'] = $this->getContributionTrackingId( $this->row['Invoice'] );
		$msg['country'] = $this->row['Country'];
		$msg['currency'] = $this->row['original_currency'] = $this->row['currency'];
		$msg['email'] = $this->row['User Mail'];
		// settled_fee since it's given in USD
		$msg['settled_fee_amount'] = -$this->row['Fee'];
		$msg['settled_total_amount'] = $this->row['Amount (USD)'];
		$msg['settled_net_amount'] = $msg['settled_total_amount'] + $msg['settled_fee_amount'];
		$msg['gateway_txn_id'] = $this->row['Reference'];
		$msg['invoice_id'] = $this->row['Invoice'];
		$msg['original_total_amount'] = $this->row['Net Amount (local)'];
		$msg['exchange_rate'] = $msg['settled_total_amount'] / $msg['original_total_amount'];

		[ $method, $submethod ] = ReferenceData::decodePaymentMethod(
			$this->row['Payment Method Type'],
			$this->row['Payment Method']
		);
		$msg['payment_method'] = $method;
		$msg['payment_submethod'] = $submethod;
		if ( $this->row['Settlement date'] ) {
			$msg['settled_date'] = UtcDate::getUtcTimestamp( $this->row['Settlement date'] );
		}
		if ( $this->row['Amount (USD)'] ) {
			$msg['settled_currency'] = 'USD';
			$msg['settled_gross'] = $this->row['Amount (USD)'];
		}
	}

}
