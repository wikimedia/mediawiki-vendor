<?php namespace SmashPig\PaymentProviders\Adyen\Audit;

use SmashPig\Core\UtcDate;
use SmashPig\PaymentProviders\Adyen\AdyenCurrencyRoundingHelper;

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
		// T306944
		// We were saving the Capture ID for 2+ recurrings in civi which for settled payments is in the
		// Modification reference. Adding this lets us match donations until the data is cleaned up
		$msg['modification_reference'] = $row['Modification Reference'];

		$msg['currency'] = $msg['original_currency'] = $row['Gross Currency'];
		$msg['settled_currency'] = $row['Net Currency'];
		$msg['gross'] = $row['Gross Credit (GC)'];
		$msg['original_total_amount'] = (float)$msg['gross'];
		// fee is given in settlement currency
		// but queue consumer expects it in original
		$msg['settled_fee_amount'] = AdyenCurrencyRoundingHelper::round( -$this->getFee( $row ), $msg['settled_currency'] );
		$msg['fee'] = AdyenCurrencyRoundingHelper::round( $this->getFee( $row ) / $msg['exchange_rate'], $msg['original_currency'] );
		$msg['original_fee_amount'] = -$msg['fee'];
		$msg['original_net_amount'] = AdyenCurrencyRoundingHelper::round( $msg['original_total_amount'] + $msg['original_fee_amount'], $msg['original_currency'] );

		// shouldn't this be settled_net or settled_amount?
		$msg['settled_gross'] = $row['Net Credit (NC)'] ?: -$row['Net Debit (NC)'];
		// Settled amount is like settled gross but is negative where negative.
		$msg['settled_net_amount'] = $row['Net Credit (NC)'] ?: -$row['Net Debit (NC)'];
		$msg['settled_total_amount'] = AdyenCurrencyRoundingHelper::round( $msg['settled_net_amount'] - $msg['settled_fee_amount'], $msg['settled_currency'] );
		return $msg;
	}

	protected function parseRefund( array $row, array $msg ): array {
		$msg['gross'] = $row['Gross Debit (GC)']; // Actually paid to donor
		$msg['original_currency'] = $msg['gross_currency'] = $row['Gross Currency'];
		$msg['settled_currency'] = $row['Net Currency'];
		// 'Net Debit (NC)' is the amount we paid including fees
		// 'Net Currency' is the currency we paid in
		$msg['settled_net_amount'] = -( $row['Net Debit (NC)'] );
		$msg = $this->parseCommonRefundValues( $row, $msg, $row['Type'], $row['Modification Reference'] );
		return $msg;
	}

	protected function getFee( array $row ): float {
		return floatval( $row['Commission (NC)'] ) +
			floatval( $row['Markup (NC)'] ) +
			floatval( $row['Scheme Fees (NC)'] ) +
			floatval( $row['Interchange (NC)'] );
	}

	protected function getFeeTransaction( array $row ): ?array {
		return [
			'settled_date' => UtcDate::getUtcTimestamp( $row[$this->date], $row['TimeZone'] ),
			'date' => UtcDate::getUtcTimestamp( $row[$this->date], $row['TimeZone'] ),
			'gateway' => 'adyen',
			'type' => 'fee',
			'gateway_txn_id' => $row['Modification Reference'],
			'gateway_account' => $row['Merchant Account'],
			'invoice_id' => $row['Merchant Reference'],
			'settlement_batch_reference' => $row['Batch Number'] ?? null,
			// In this context the total amount is what is paid by the donor - ie nothing.
			// The net_amount is what is paid to us - ie a negative value equal to the fee_amount.
			'settled_total_amount' => 0,
			'settled_fee_amount' => '-' . $row['Net Debit (NC)'],
			'settled_net_amount' => '-' . $row['Net Debit (NC)'],
			'audit_file_gateway' => 'adyen',
			'settled_currency' => $row['Net Currency'],
		];
	}

	protected function getPayoutTransaction( array $row ): ?array {
		return [
			'settled_date' => UtcDate::getUtcTimestamp( $row[$this->date], $row['TimeZone'] ),
			'date' => UtcDate::getUtcTimestamp( $row[$this->date], $row['TimeZone'] ),
			'gateway' => 'adyen',
			'type' => 'payout',
			'gateway_txn_id' => $row['Modification Reference'],
			'gateway_account' => $row['Merchant Account'],
			'invoice_id' => $row['Merchant Reference'],
			'settlement_batch_reference' => $row['Batch Number'] ?? null,
			'settled_total_amount' => $row['Net Debit (NC)'],
			'settled_currency' => $row['Net Currency'],
		];
	}

}
