<?php namespace SmashPig\PaymentProviders\Adyen\Audit;

use SmashPig\PaymentProviders\Adyen\AdyenCurrencyRoundingHelper;

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
		$msg['currency'] = $msg['original_currency'] = $row['Payment Currency'];
		$msg['original_total_amount'] = $msg['gross'] = $row['Original Amount'];
		// fee is given in settlement currency
		// but queue consumer expects it in original
		$exchange = $row['Exchange Rate'];
		// The exchange rate can be empty for Settled Externally (amex)
		if ( $exchange == "" ) {
			$exchange = 1;
		}
		$fee = $msg['settled_fee_amount'] = $this->getFee( $row );
		$msg['original_fee_amount'] = $msg['fee'] = AdyenCurrencyRoundingHelper::round( $fee / $exchange, $msg['original_currency'] );

		// shouldn't this be settled_net or settled_amount?
		$msg['settled_gross'] = $row['Payable (SC)'];
		$msg['settled_net_amount'] = $msg['settled_gross'];
		$msg['settled_currency'] = $row['Settlement Currency'];
		$msg['settled_fee_amount'] = -$fee;
		$msg['original_net_amount'] = AdyenCurrencyRoundingHelper::round( $msg['original_total_amount'] - $msg['original_fee_amount'], $msg['original_currency'] );
		$msg['settled_total_amount'] = AdyenCurrencyRoundingHelper::round( $msg['settled_net_amount'] - $msg['settled_fee_amount'], $msg['settled_currency'] );
		return $msg;
	}

	protected function parseRefund( array $row, array $msg ): array {
		// For refunds, captured (PC) and Original Amount both have the amount refunded
		// For some currencies (JPY) Original Amount seems to be off by 100x
		if ( !empty( $row['Captured (PC)'] ) ) {
			$msg['gross'] = $row['Captured (PC)'];
			$msg['gross_currency'] = $msg['original_currency'] = $row['Payment Currency'];
		} else {
			// For chargebacks, subtract off a couple fees to get the same number as we're getting from
			// the settlement report's 'Gross Debit' field.
			$msg['gross_currency'] = $msg['original_currency'] = $row['Main Currency'];
			// Doing math on floats, need to round to thousandths place to keep sanity
			$msg['gross'] = round(
				(float)$row['Main Amount'] - $this->getFee( $row ), 3
			);
		}
		$msg['settled_net_amount'] = -( $row['Main Amount'] );
		$msg['settled_currency'] = $row['Settlement Currency'];
		$msg = $this->parseCommonRefundValues( $row, $msg, $row['Record Type'], $row['Modification Psp Reference'] );
		// This has not been historically set & is a bit ambiguous. Phase it out rather than add it.
		unset( $msg['fee'] );
		return $msg;
	}

	protected function getFee( array $row ): float {
		return (float)$row['Markup (SC)']
			+ (float)$row['Interchange (SC)']
			+ (float)$row['Scheme Fees (SC)']
			+ (float)$row['Commission (SC)'];
	}

}
