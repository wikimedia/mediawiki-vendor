<?php namespace SmashPig\PaymentProviders\Adyen\Audit;

class AdyenSettlementDetailReport extends AdyenAudit {

	public function __construct() {
		$this->columnHeaders = [
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

		$this->type = 'Type';
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

		return $msg;
	}

	protected function parseRefund( array $row, array $msg ): array {
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

		return $msg;
	}
}
