<?php namespace SmashPig\PaymentProviders\Adyen\Audit;

class AdyenPaymentsAccountingReport extends AdyenAudit {

	public function __construct() {
		$this->columnHeaders = [
			'Company Account',
			'Merchant Account',
			'Psp Reference',
			'Merchant Reference',
			'Payment Method',
			'Payment Method Variant',
			'Creation Date',
			'Record Type',
			'Modification Psp Reference', // this is the Modification Reference
			'Main Currency',
			'Main Amount',
			'Payment Currency',
			'Received (PC)',
			'Exchange Rate',
			'Authorised (PC)',
			'Captured (PC)',
			'Settlement Currency',
			'Payable (SC)',
			'Commission (SC)',
			'Markup (SC)',
			'Scheme Fees (SC)',
			'Interchange (SC)',
			'Processing Fee Currency',
			'Processing Fee (FC)',
			'Modification Merchant Reference',
			'Original Amount',
			'Merchant Order Reference',
			'Shopper Reference',
			'Reserved3',
			'Reserved4',
			'Reserved5',
			'Reserved6',
			'Reserved7',
			'Reserved8',
			'Reserved9',
			'Reserved10',
		];
		$this->type = 'Record Type';
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
		// Captured (PC) and Original Amount both have the amount refunded
		// For some currencies (JPY) Original Amount seems to be off by 100x
		$msg['gross'] = $row['Captured (PC)'];
		$msg['gross_currency'] = $row['Payment Currency'];

		$msg['gateway_parent_id'] = $row['Psp Reference'];
		$msg['gateway_refund_id'] = $row['Modification Psp Reference'];
		if ( strtolower( $row['Record Type'] ) === 'chargeback' ) {
			$msg['type'] = 'chargeback';
		} else {
			$msg['type'] = 'refund';
		}

		return $msg;
	}

}
