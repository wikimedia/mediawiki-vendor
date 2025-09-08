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

		$msg['currency'] = $msg['original_currency'] = $row['Gross Currency'];
		$msg['gross'] = $row['Gross Credit (GC)'];
		$msg['original_total_amount'] = (float)$msg['gross'];
		// fee is given in settlement currency
		// but queue consumer expects it in original
		$msg['settled_fee_amount'] = $this->getFee( $row );
		$msg['fee'] = round( $msg['settled_fee_amount'] / $msg['exchange_rate'], 2 );
		$msg['original_fee_amount'] = $msg['fee'];
		$msg['original_net_amount'] = $msg['original_total_amount'] - $msg['original_fee_amount'];

		// shouldn't this be settled_net or settled_amount?
		$msg['settled_gross'] = $row['Net Credit (NC)'];
		$msg['settled_currency'] = $row['Net Currency'];
		// Settled amount is like settled gross but is negative where negative.
		$msg['settled_net_amount'] = (float)$row['Net Credit (NC)'];
		$msg['settled_total_amount'] = round( $msg['settled_net_amount'] + $msg['settled_fee_amount'], 2 );
		$msg['settled_date'] = empty( $row['Booking Date'] ) ? null : UtcDate::getUtcTimestamp( $row['Booking Date'], $row['Booking Date TimeZone'] );
		return $msg;
	}

	protected function parseRefund( array $row, array $msg ): array {
		$msg['gross'] = $row['Gross Debit (GC)']; // Actually paid to donor
		$msg['original_currency'] = $msg['gross_currency'] = $row['Gross Currency'];
		$msg['settled_currency'] = $row['Net Currency'];
		$msg['settled_date'] = empty( $row['Booking Date'] ) ? null : UtcDate::getUtcTimestamp( $row['Booking Date'], $row['Booking Date TimeZone'] );
		// This is REALLY confusing - but in the Adyen csv
		// we can find (e.g) a USD row like
		// Net Debit (NC)   = 15.65 (settled_total_amount)
		// Markup (NC)      = 10.65 (settled_fee_amount)
		// Gross Debit (GC) = 5     (settled_net_amount)
		// In this case we hit a chargeback where $5 USD was returned to the donor
		// We were charged $10.65 as a charge back penantly and
		// $15.65 is charged to us in total. If it were not USD Gross Debit (GC) would be
		// in the original currency.
		$msg['settled_total_amount'] = -( $row['Net Debit (NC)'] );
		$msg['settled_fee_amount'] = $this->getFee( $row ) > 0 ? -( $this->getFee( $row ) ) : 0;
		$msg['settled_net_amount'] = $msg['settled_total_amount'] - $msg['settled_fee_amount'];
		$msg['fee'] = $msg['settled_fee_amount'] ? round( $msg['settled_fee_amount'] / $msg['exchange_rate'], 2 ) : 0;
		$msg['original_net_amount'] = $msg['gross'] > 0 ? -( (float)$msg['gross'] ) : (float)( $msg['gross'] );
		$msg['original_fee_amount'] = $msg['fee'] > 0 ? -( (float)$msg['fee'] ) : (float)( $msg['fee'] );
		$msg['original_total_amount'] = $msg['original_net_amount'] + $msg['original_fee_amount'];
		// 'Net Debit (NC)' is the amount we paid including fees
		// 'Net Currency' is the currency we paid in
		// Deal with these when queue consumer can understand them
		if ( $this->isOrchestratorMerchantReference( $row ) ) {
			$msg['backend_processor_parent_id'] = $row['Psp Reference'];
			$msg['backend_processor_refund_id'] = $row['Modification Reference'];
		} else {
			$msg['gateway_parent_id'] = $row['Psp Reference'];
			$msg['gateway_refund_id'] = $row['Modification Reference'];
		}
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

	protected function getFeeTransaction( array $row ): ?array {
		return [
			'settled_date' => UtcDate::getUtcTimestamp( $row[$this->date], $row['TimeZone'] ),
			'gateway' => 'adyen',
			'type' => 'fee',
			'gateway_account' => $row['Merchant Account'],
			'invoice_id' => $row['Merchant Reference'],
			'settlement_batch_reference' => $row['Batch Number'] ?? null,
			'settled_fee_amount' => $this->getFee( $row ) > 0 ? -( $this->getFee( $row ) ) : 0,
		];
	}
}
