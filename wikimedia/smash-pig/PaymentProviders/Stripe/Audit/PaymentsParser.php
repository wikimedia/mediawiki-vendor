<?php

namespace SmashPig\PaymentProviders\Stripe\Audit;

// Parser for balance_change_from_activity report exports.
// Docs: https://docs.stripe.com/reports/report-types/balance-change-from-activity
class PaymentsParser extends BaseParser {
	protected function getSettledDateFields(): array {
		return [
			'effective_at_utc',
			'effective_at',
			'automatic_payout_effective_at_utc',
			'automatic_payout_effective_at',
			'available_on_utc',
			'available_on',
		];
	}

	protected function getOriginalCurrencyFields(): array {
		return [
			'currency' => $this->normalizeCurrency( $this->row['currency'] ?? null ),
			'gross' => $this->firstNonEmpty( $this->row['gross'] ?? null, $this->row['amount'] ?? null ),
			'original_total_amount' => $this->firstNonEmpty( $this->row['gross'] ?? null, $this->row['amount'] ?? null ),
			'original_currency' => $this->normalizeCurrency( $this->row['currency'] ?? null ),
		];
	}
}
