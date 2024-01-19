<?php namespace SmashPig\PaymentProviders\Adyen\Audit;

class AdyenPaymentsAccountingReport extends AdyenAudit {

	public function __construct() {
		$this->requiredColumns += [
			'Booking Date',
			'Record Type',
			'Modification Psp Reference', // this is the Modification Reference
			'Payment Currency',
			'Exchange Rate',
			'Captured (PC)',
			'Settlement Currency',
			'Payable (SC)',
			'Commission (SC)',
			'Markup (SC)',
			'Scheme Fees (SC)',
			'Interchange (SC)',
			'Original Amount',
		];

		$this->type = 'Record Type';
		// in this report the latest payment status date is in Booking Date, it has the same value as
		// Creation Date for the settlement detail report
		$this->date = 'Booking Date';
	}

	protected function parseDonation( array $row, array $msg ): array {
		$msg['modification_reference'] = $row['Modification Psp Reference'];
		$msg['currency'] = $row['Payment Currency'];
		$msg['gross'] = $row['Original Amount'];
		// fee is given in settlement currency
		// but queue consumer expects it in original
		$exchange = $row['Exchange Rate'];
		// The exchange rate can be empty for Settled Externally (amex)
		if ( $exchange == "" ) {
			$exchange = 1;
		}
		$fee = floatval( $row['Commission (SC)'] ) +
			floatval( $row['Markup (SC)'] ) +
			floatval( $row['Scheme Fees (SC)'] ) +
			floatval( $row['Interchange (SC)'] );
		$msg['fee'] = round( $fee / $exchange, 2 );
		// shouldn't this be settled_net or settled_amount?
		$msg['settled_gross'] = $row['Payable (SC)'];
		$msg['settled_currency'] = $row['Settlement Currency'];
		$msg['settled_fee'] = $fee;

		return $msg;
	}

	protected function parseRefund( array $row, array $msg ): array {
		// For refunds, captured (PC) and Original Amount both have the amount refunded
		// For some currencies (JPY) Original Amount seems to be off by 100x
		if ( !empty( $row['Captured (PC)'] ) ) {
			$msg['gross'] = $row['Captured (PC)'];
			$msg['gross_currency'] = $row['Payment Currency'];
		} else {
			// For chargebacks, subtract off a couple fees to get the same number as we're getting from
			// the settlement report's 'Gross Debit' field.
			$msg['gross_currency'] = $row['Main Currency'];
			// Doing math on floats, need to round to thousandths place to keep sanity
			$msg['gross'] = round(
				$row['Main Amount'] - $row['Markup (SC)'] - $row['Interchange (SC)'], 3
			);
		}

		$msg['gateway_parent_id'] = $row['Psp Reference'];
		$msg['gateway_refund_id'] = $row['Modification Psp Reference'];
		if ( in_array( strtolower( $row['Record Type'] ), [ 'chargeback', 'secondchargeback' ] ) ) {
			$msg['type'] = 'chargeback';
		} else {
			$msg['type'] = 'refund';
		}

		return $msg;
	}

}
