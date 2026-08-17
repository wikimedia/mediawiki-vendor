<?php

namespace SmashPig\PaymentProviders\CheckoutCom\Audit;

use Brick\Math\RoundingMode;
use Brick\Money\Money;
use SmashPig\Core\Helpers\Base62Helper;
use SmashPig\Core\Helpers\CurrencyRoundingHelper;

class SettlementBreakdownReport extends CheckoutComAudit {

	protected array $row;
	protected array $feeRows;

	public function __construct( array $row, array $feeRows ) {
		$this->row = $row;
		$this->feeRows = $feeRows;
	}

	/**
	 * @return float
	 */
	public function getExchangeRate(): float {
		return (float)( $this->row['FX Rate Applied'] ?: 1 );
	}

	public function getSettledNetAmountRounded(): string {
		$netAmount = Money::of( $this->getSettledTotalAmountRounded(), $this->getSettledCurrency() )
		  ->plus( $this->getSettledFees() );

		// Now check our calculated value against the reported value in the CSV.
		// We expect it to differ by no more than 1 cent in either direction.
		$reported = Money::of(
			$this->row['Net In Holding Currency'],
			$this->getSettledCurrency(),
			null,
			RoundingMode::HalfUp
		);
		foreach ( $this->feeRows as $feeRow ) {
			$reported = $reported->plus( $feeRow['Net In Holding Currency'] );
		}

		$difference = $reported->minus( $netAmount )->getMinorAmount()->toInt();

		if ( abs( $difference ) > 1 ) {
			throw new \RuntimeException( sprintf(
				'Rounded net calculation differs from CSV by %d minor units. ' .
				'CSV=%s Calculated=%s Payment=%s',
				$difference,
				$reported->getAmount(),
				$netAmount->getAmount(),
				$this->row['Payment ID'] ?? '(unknown)'
			) );
		}

		return (string)$netAmount->getAmount();
	}

	/**
	 * @return string
	 */
	public function getSettledFeeAmountRounded(): string {
		return (string)$this->getSettledFees()->getAmount();
	}

	public function getSettledFees(): Money {
		$fees = Money::of( $this->row['Deduction In Holding Currency'], $this->getSettledCurrency(), null, RoundingMode::HalfUp );
		foreach ( $this->feeRows as $row ) {
			$fees = $fees->plus( $row['Deduction In Holding Currency'], RoundingMode::HalfUp );
		}
		return $fees;
	}

	public function getOriginalFees(): Money {
		return $this->getSettledFees()->convertedTo( $this->getOriginalCurrency(), (string)$this->getExchangeRate(), null, RoundingMode::HalfUp );
	}

	/**
	 * @return string
	 */
	public function getOriginalFeeAmountRounded(): string {
		return (string)$this->getOriginalFees()->getAmount();
	}

	/**
	 * @return string
	 */
	public function getSettledTotalAmountRounded(): string {
		return CurrencyRoundingHelper::round( $this->row['Gross In Holding Currency'], $this->getSettledCurrency() );
	}

	/**
	 * @return string
	 */
	public function getSettledCurrency(): string {
		return $this->row['Holding Currency'];
	}

	/**
	 * @return string
	 */
	public function getOriginalCurrency(): string {
		return $this->row['Processing Currency'];
	}

	public function getOriginalTotalAmountRounded(): string {
		return CurrencyRoundingHelper::round( $this->row['Gross In Processing Currency'], $this->getOriginalCurrency() );
	}

	public function getOriginalNetAmountRounded(): string {
		$netAmount = Money::of( $this->getOriginalTotalAmountRounded(), $this->getOriginalCurrency() )
			->plus( $this->getOriginalFeeAmountRounded() );

		return (string)$netAmount->getAmount();
	}

	/**
	 * @param array<string,string|null> $row
	 * @return array<string,mixed>
	 */
	protected function parseDonation( array $row ): array {
		$msg = $this->setCommonValues( $row );
		$msg['type'] = 'donation';
		$msg['currency'] = $msg['original_currency'] = $this->getOriginalCurrency();
		$msg['settled_currency'] = $this->getSettledCurrency();
		$msg['original_total_amount'] = $msg['gross'] = $this->getOriginalTotalAmountRounded();
		$msg['original_fee_amount'] = $this->getOriginalFeeAmountRounded();
		$msg['original_net_amount'] = $this->getOriginalNetAmountRounded();
		$msg['settled_fee_amount'] = $this->getSettledFeeAmountRounded();
		$msg['settled_net_amount'] = $this->getSettledNetAmountRounded();
		$msg['settled_total_amount'] = $this->getSettledTotalAmountRounded();

		return $msg;
	}

	/**
	 * @param array<string,string|null> $row
	 * @param string $type
	 * @return array<string,mixed>
	 */
	protected function parseRefund( array $row, string $type ): array {
		$msg = $this->setCommonValues( $row );
		$msg['type'] = $type === 'chargeback' ? 'chargeback' : 'refund';
		$msg['gross'] = abs( $this->amount( $row['Gross In Processing Currency'], $this->getOriginalCurrency() ) );
		$msg['gross_currency'] = $msg['original_currency'] = $this->getOriginalCurrency();
		$msg['backend_processor_parent_id'] = $row['Payment ID'];
		$msg['backend_processor_reversal_id'] = $row['Payment ID'];
		$msg['original_total_amount'] = -abs( $this->amount( $row['Gross In Processing Currency'], $this->getOriginalCurrency() ) );
		$msg['original_fee_amount'] = $this->getOriginalFeeAmountRounded();
		$msg['original_net_amount'] = $this->getOriginalNetAmountRounded();
		$msg['settled_fee_amount'] = $this->getSettledFeeAmountRounded();
		$msg['settled_net_amount'] = $this->amount( $row['Net In Holding Currency'], $this->getSettledCurrency() );
		$msg['settled_total_amount'] = $this->getSettledTotalAmountRounded();
		$msg['settled_currency'] = $this->getSettledCurrency();

		return $msg;
	}

	/**
	 * @param array<string,string|null> $row
	 * @return array<string,mixed>
	 */
	protected function getFeeTransaction( array $row ): array {
		$amount = $this->amount( $row['Net In Holding Currency'], $row['Holding Currency'] );

		return [
			'gateway' => 'checkoutcom',
			'audit_file_gateway' => 'checkoutcom',
			'type' => 'fee',
			// Payment ID may be the same as for a relevant donation so make it more unique.
			'gateway_txn_id' => 'fee-' . $row['Payment ID'] . strtotime( ( (string)$row['Processed On'] ) ) . $row['Type'],
			'gateway_account' => $row['Processing Channel Name'],
			'settlement_batch_reference' => $row['Payout ID'],
			'date' => $this->getUtcTimestamp( $row['Processed On'] ),
			'settled_date' => $this->getUtcTimestamp( $row['Available On'] ),
			'settled_currency' => $row['Holding Currency'],
			'settled_total_amount' => '0.00',
			'settled_fee_amount' => $amount,
			'settled_net_amount' => $amount,
		];
	}

	/**
	 * @param array<string,string|null> $row
	 * @return array<string,mixed>
	 */
	protected function getPayoutTransaction( array $row ): array {
		return [
			'gateway' => 'checkoutcom',
			'audit_file_gateway' => 'checkoutcom',
			'type' => 'payout',
			'gateway_txn_id' => $row['Payout ID'],
			'gateway_account' => $row['Processing Channel Name'],
			'settlement_batch_reference' => $row['Payout ID'],
			'date' => $this->getUtcTimestamp( $row['Processed On'] ),
			'settled_date' => $this->getUtcTimestamp( $row['Available On'] ),
			'settled_currency' => $row['Holding Currency'],
			'settled_total_amount' => $this->amount( $row['Net In Holding Currency'], $row['Holding Currency'] ),
		];
	}

	/**
	 * @param array<string,string|null> $row
	 * @return array<string,mixed>
	 */
	protected function setCommonValues( array $row ): array {
		[ $paymentMethod, $paymentSubmethod ] = $this->decodePaymentMethod( $row['Payment Method'] ?? '' );

		return [
			'gateway' => 'gravy',
			'audit_file_gateway' => 'checkoutcom',
			'backend_processor' => 'checkoutcom',
			'gateway_account' => $row['Processing Channel Name'],
			'gateway_txn_id' => $this->getGatewayTxnId( $row['Reference'] ),
			'backend_processor_txn_id' => $row['Payment ID'],
			'auth_id' => $row['Payment ID'],
			'payment_orchestrator_reconciliation_id' => $row['Reference'],
			'settlement_batch_reference' => $row['Payout ID'],
			'payment_method' => $paymentMethod,
			'payment_submethod' => $paymentSubmethod,
			'date' => $this->getUtcTimestamp( $row['Processed On'] ),
			'settled_date' => $this->getUtcTimestamp( $row['Available On'] ),
			'exchange_rate' => $this->getExchangeRate(),
		];
	}

	/**
	 * Gravy gives us the payment_orchestrator_reconciliation_id in the report.
	 * Audit wants gateway_txn_id in the same base62 form used by contribution_extra.gateway_txn_id.
	 */
	protected function getGatewayTxnId( ?string $orchestratorReconciliationId ): ?string {
		if ( $orchestratorReconciliationId === null || $orchestratorReconciliationId === '' ) {
			return null;
		}

		return Base62Helper::toUuid( $orchestratorReconciliationId );
	}

	protected function getUtcTimestamp( ?string $date ): ?int {
		if ( $date === null || $date === '' ) {
			return null;
		}
		return ( new \DateTimeImmutable( $date, new \DateTimeZone( 'UTC' ) ) )->getTimestamp();
	}

	protected function amount( ?string $value, $currency ): string {
		return CurrencyRoundingHelper::round( (float)$value, $currency );
	}

	/**
	 * @return array{0:string,1:string|null}
	 */
	protected function decodePaymentMethod( ?string $paymentMethod ): array {
		switch ( strtolower( $paymentMethod ?? '' ) ) {
			case 'visa':
				return [ 'cc', 'visa' ];
			case 'mastercard':
				return [ 'cc', 'mc' ];
			case 'amex':
				return [ 'cc', 'amex' ];
			case 'discover':
				return [ 'cc', 'discover' ];
			default:
				return [ 'cc', null ];
		}
	}
}
