<?php

namespace SmashPig\PaymentProviders\Stripe\Audit;

/**
 * Parser for settlement-report and settlement-api files.
 * The date precedence covers both the Stripe report export and the direct API
 * settlement CSV shape.
 */
class SettlementParser extends BaseParser {
	protected function getSettledDateFields(): array {
		return [
			'automatic_payout_effective_at_utc',
			'automatic_payout_effective_at',
			'available_on_utc',
			'available_on',
		];
	}

	protected function getSettlementFields(): array {
		return [
			'settlement_batch_reference' => $this->row['automatic_payout_id'],
			'settled_total_amount' => $this->firstNonEmpty( $this->row['gross'] ?? null, $this->row['amount'] ?? null ),
			'settled_fee_amount' => $this->firstNonEmpty( $this->row['fee'] ?? null, $this->row['tax'] ?? null ),
			'settled_net_amount' => $this->firstNonEmpty( $this->row['net'] ?? null, $this->row['amount'] ?? null ),
			'settled_currency' => $this->normalizeCurrency( $this->row['currency'] ?? null ),
			'settled_date' => $this->toUtcTimestamp( $this->firstNonEmpty( ...$this->extractFields( $this->getSettledDateFields() ) ) ),
		];
	}

	private function extractFields( array $fields ): array {
		$values = [];
		foreach ( $fields as $field ) {
			$values[] = $this->row[$field] ?? null;
		}
		return $values;
	}

}
