<?php

namespace SmashPig\PaymentProviders\Overflow\Audit;

use SmashPig\Core\DataFiles\AuditParser;
use SmashPig\Core\Helpers\CurrencyRoundingHelper;
use SmashPig\Core\UtcDate;
use SmashPig\PaymentProviders\Overflow\Contribution;
use SmashPig\PaymentProviders\Overflow\Deposit;

/**
 * Parser for the combined deposit/contribution/summary JSON files written by
 * PaymentProviders/Overflow/Maintenance/GetReport.php - one file per deposit,
 * one output row per contribution in that deposit.
 */
class DepositsAudit implements AuditParser {

	private const CURRENCY = 'USD';

	public function parseFile( string $path ): array {
		$data = json_decode( file_get_contents( $path ), true, 512, JSON_THROW_ON_ERROR );
		if ( !is_array( $data ) ) {
			throw new \UnexpectedValueException( 'Overflow audit file did not contain a JSON object: ' . $path );
		}

		$deposit = new Deposit( $data );
		// Throws if the deposit no longer reconciles internally, for example
		// because a contribution was cancelled after being included in the
		// deposit's line items. Such deposits need manual investigation
		// rather than being partially imported.
		$deposit->validate();

		$rows = [];
		foreach ( $deposit->getContributions() as $contribution ) {
			$rows[] = $this->normalizeContribution( $deposit, $contribution );
		}
		$rows[] = $this->buildPayoutRow( $deposit );
		return $rows;
	}

	/**
	 * A 'payout' row verifies the batch total.
	 */
	private function buildPayoutRow( Deposit $deposit ): array {
		$settledDate = $this->toUtcTimestamp( $deposit->getArrivalAt() );

		$row = [
			'gateway' => 'overflow',
			'audit_file_gateway' => 'overflow',
			'type' => 'payout',
			'gateway_txn_id' => $deposit->getId(),
			'gateway_account' => $deposit->getGatewayAccount(),
			'settlement_batch_reference' => $deposit->getSettlementBatchReference(),
			'settled_currency' => self::CURRENCY,
			'settled_total_amount' => $deposit->getAmount(),
			'settled_net_amount' => $deposit->getAmount(),
			'settled_fee_amount' => '0.00',
			'settled_date' => $settledDate,
			'date' => $settledDate,
		];

		return array_filter( $row, static fn ( $value ) => $value !== null && $value !== '' );
	}

	private function normalizeContribution( Deposit $deposit, Contribution $contribution ): array {
		// The contribution's own gross amount is the true gross. The line
		// item "grossValueInCents" is, despite its name, the amount actually
		// transferred for this contribution (i.e. net of Overflow's fee) -
		// Overflow's API only exposes fees in aggregate at the deposit level.
		$grossAmount = $contribution->getAmountInMinorUnits();
		$netAmount = $contribution->getLineItemGrossAmountInMinorUnits();
		$feeAmount = $netAmount - $grossAmount;

		$msg = [
			'gateway' => 'overflow',
			'audit_file_gateway' => 'overflow',
			'type' => 'donation',
			'gateway_account' => $deposit->getGatewayAccount(),
			// contributionReceivedAt (when Overflow received/liquidated the gift)
			// isn't always present - e.g. DAF gifts don't get liquidated - so
			// fall back to the donor's original contribution date.
			'date' => $this->toUtcTimestamp( $contribution->getContributionReceivedAt() ?: $contribution->getContributionDate() ),
			'settled_date' => $this->toUtcTimestamp( $deposit->getArrivalAt() ),
			'gateway_txn_id' => $contribution->getId(),
			'backend_processor' => 'overflow',
			'backend_processor_txn_id' => $contribution->getId(),
			'payment_method' => $this->mapPaymentMethod( $contribution->getPaymentMethodType() ),
			'original_currency' => self::CURRENCY,
			'original_total_amount' => $contribution->getAmount(),
			'settled_currency' => self::CURRENCY,
			'settled_total_amount' => $contribution->getAmount(),
			'settled_fee_amount' => CurrencyRoundingHelper::getAmountInMajorUnits( $feeAmount, self::CURRENCY ),
			'settled_net_amount' => CurrencyRoundingHelper::getAmountInMajorUnits( $netAmount, self::CURRENCY ),
			'settlement_batch_reference' => $deposit->getSettlementBatchReference(),
			'first_name' => $contribution->getFirstName(),
			'last_name' => $contribution->getLastName(),
			'email' => $contribution->getEmail(),
			'phone' => $contribution->getPhone(),
			'street_address' => $contribution->getStreetAddress(),
			'supplemental_address_1' => $contribution->getSupplementalAddress(),
			'city' => $contribution->getCity(),
			'state_province' => $contribution->getStateProvince(),
			'postal_code' => $contribution->getPostalCode(),
			'country' => $contribution->getCountry(),
			'stock_ticker' => implode( ',', $contribution->getStockTickers() ),
			'stock_quantity' => $contribution->getStockQuantity(),
		];

		return array_filter( $msg, static fn ( $value ) => $value !== null && $value !== '' );
	}

	private function mapPaymentMethod( string $overflowPaymentMethodType ): string {
		return $overflowPaymentMethodType === 'DAF' ? 'DAFpay' : $overflowPaymentMethodType;
	}

	private function toUtcTimestamp( ?string $value ): ?int {
		if ( $value === null || trim( $value ) === '' ) {
			return null;
		}
		return UtcDate::getUtcTimestamp( $value );
	}
}
