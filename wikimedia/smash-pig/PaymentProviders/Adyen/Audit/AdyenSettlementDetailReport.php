<?php namespace SmashPig\PaymentProviders\Adyen\Audit;

use SmashPig\Core\UtcDate;

class AdyenSettlementDetailReport extends AdyenAudit {

	public function __construct() {
		$this->columnHeaders = [
			'Creation Date',
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
			'Booking Date',
			'Booking Date TimeZone',
		];

		$this->type = 'Type';
		$this->date = 'Creation Date';
	}

	protected function parseDonation( array $row, array $msg ): array {
		$msg['gateway_txn_id'] = $row['Psp Reference'];
		// T306944
		// We were saving the Capture ID for 2+ recurrings in civi which for settled payments is in the
		// Modification reference. Adding this lets us match donations until the data is cleaned up
		$msg['modification_reference'] = $row['Modification Reference'];

		$msg['currency'] = $row['Gross Currency'];
		$msg['gross'] = $row['Gross Credit (GC)'];
		// fee is given in settlement currency
		// but queue consumer expects it in original
		$msg['settled_fee'] = $this->getFee( $row );
		$msg['fee'] = round( $msg['settled_fee'] / $msg['exchange_rate'], 2 );

		// shouldn't this be settled_net or settled_amount?
		$msg['settled_gross'] = $row['Net Credit (NC)'];
		$msg['settled_currency'] = $row['Net Currency'];
		// Settled amount is like settled gross but is negative where negative.
		$msg['settled_amount'] = $row['Net Credit (NC)'];
		$msg['settled_date'] = empty( $row['Booking Date'] ) ? null : UtcDate::getUtcTimestamp( $row['Booking Date'], $row['Booking Date TimeZone'] );
		return $msg;
	}

	protected function parseRefund( array $row, array $msg ): array {
		$msg['gross'] = $row['Gross Debit (GC)']; // Actually paid to donor
		$msg['gross_currency'] = $row['Gross Currency'];
		$msg['settled_currency'] = $row['Net Currency'];
		$msg['settled_amount'] = -( $row['Net Debit (NC)'] );
		$msg['settled_date'] = empty( $row['Booking Date'] ) ? null : UtcDate::getUtcTimestamp( $row['Booking Date'], $row['Booking Date TimeZone'] );
		$msg['settled_fee'] = $this->getFee( $row ) > 0 ? -( $this->getFee( $row ) ) : 0;
		$msg['fee'] = $msg['settled_fee'] ? round( $msg['settled_fee'] / $msg['exchange_rate'], 2 ) : 0;
		// 'Net Debit (NC)' is the amount we paid including fees
		// 'Net Currency' is the currency we paid in
		// Deal with these when queue consumer can understand them

		$msg['gateway_parent_id'] = $row['Psp Reference'];
		$msg['gateway_refund_id'] = $row['Modification Reference'];
		if ( in_array( strtolower( $row['Type'] ), [ 'chargeback', 'secondchargeback' ] ) ) {
			$msg['type'] = 'chargeback';
		} else {
			$msg['type'] = 'refund';
		}

		return $msg;
	}

	private function getFee( array $row ): float {
		return floatval( $row['Commission (NC)'] ) +
			floatval( $row['Markup (NC)'] ) +
			floatval( $row['Scheme Fees (NC)'] ) +
			floatval( $row['Interchange (NC)'] );
	}
}
