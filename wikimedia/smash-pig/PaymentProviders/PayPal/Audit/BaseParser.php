<?php
declare( strict_types=1 );

namespace SmashPig\PaymentProviders\PayPal\Audit;

use SmashPig\Core\Helpers\CurrencyRoundingHelper;
use SmashPig\Core\UnhandledException;

class BaseParser {

	protected array $row;
	protected array $headers;
	protected array $conversionRows;
	protected array $payouts;
	protected array $feeRows;

	public function __construct( array $row, array $headers, array $conversionRows, array $payouts, array $feeRows ) {
		$this->row = $row;
		$this->headers = $headers;
		$this->conversionRows = $conversionRows;
		$this->payouts = $payouts;
		$this->feeRows = $feeRows;
	}

	/**
	 * @return int
	 */
	public function hasConversion(): bool {
		return isset( $this->conversionRows[$this->row['Invoice ID']] );
	}

	/**
	 * @see https://developer.paypal.com/docs/reports/reference/tcodes
	 * @return string[]
	 */
	public static function getTransactionCodes(): array {
		return [
			'T0002' => 'recurring_payment',
			// In our case preapproved payment is braintree.
			'T0003' => 'preapproved_payment',
			'T0006' => 'subscription_payment',
			// This is in our tests but not really documented - it seems to be blocked
			'T0013' => 'risky_payment',
			'T0200' => 'currency_conversion',
			'T0100' => 'fee',
			// chargeback fee - this is a fee charged when a chargeback takes place
			// it is generally on the next row. It's parent id is the id of
			// the id of the chargeback transaction. It should be incorporated
			// into the chargeback.
			'T0106' => 'chargeback_fee',
			// chargeback reversal fee - this is ?sometimes? always? the reversal of a fee
			// which we have been charged - ie if there is a chargeback reversal
			// than the next row is the reversal of the fee on that charge back.
			// The chargeback is unlikely to be in the same file and the parent id
			// in the report is the id of the original fee - which might have been months
			// earlier so the Invoice ID is the preferred way to merge these into the ChargebackReversal
			// transaction.
			'T1108' => 'fee_reversal',
			// refund reversal fee
			'T1109' => 'fee_reversal',
			// payment request fee.
			'T0104' => 'fee',
			// partner fee
			'T0113' => 'fee',
			'T1106' => 'reversal',
			'T0400' => 'withdrawal',
			'T1107' => 'refund',
			'T1201' => 'chargeback',
			'T1202' => 'chargeback_reversed',
		];
	}

	protected function getTransactionType(): string {
		return self::getTransactionCodes()[$this->getTransactionCode()] ?? '';
	}

	protected function getTransactionCode(): string {
		return (string)( $this->row['Transaction Event Code'] ?? '' );
	}

	protected function isBraintreePayment(): bool {
		return $this->getTransactionType() === 'preapproved_payment';
	}

	protected function isRecurringPayment(): bool {
		return $this->getTransactionType() === 'recurring_payment';
	}

	protected function isGravy(): bool {
		$customField = $this->row['Custom Field'] ?? '';
		return strlen( $customField ) > 20 && !str_contains( $customField, '.' ) && !is_numeric( $customField );
	}

	protected function getTransactionPrefix(): string {
		$code = $this->getTransactionCode();
		return $code !== '' ? substr( $code, 0, 3 ) : '';
	}

	/**
	 * Is refund-ish type: 'refund'|'reversal'|'chargeback' .
	 */
	protected function isReversalType(): bool {
		return in_array( $this->getTransactionType(), [ 'reversal', 'refund', 'chargeback' ], true );
	}

	/**
	 * Is this a case of a reversal being reversed.
	 */
	protected function isReversalReversalType(): bool {
		return in_array( $this->getTransactionType(), [ 'chargeback_reversed' ], true );
	}

	protected function isReversalPrefix(): bool {
		$prefix = $this->getTransactionPrefix();
		return $prefix === 'T11' || $prefix === 'T12';
	}

	protected function isPaymentishPrefix(): bool {
		$prefix = $this->getTransactionPrefix();
		return in_array( $prefix, [ 'T00', 'T03', 'T05', 'T07', 'T22' ], true );
	}

	protected function isDebitPaymentToSomeoneElse(): bool {
		// Only applies to payment-ish events (not refunds/chargebacks)
		if ( !$this->isPaymentishPrefix() ) {
			return false;
		}

		// Recurring payments are handled separately.
		if ( $this->isRecurringPayment() ) {
			return false;
		}

		$drCr = (string)( $this->row['Transaction Debit or Credit'] ?? '' );
		return $drCr === 'DR';
	}

	protected function getGateway(): string {
		if ( ( $this->row['Payment Source'] ?? '' ) === 'Express Checkout' ) {
			return 'paypal_ec';
		}
		# Skating further onto thin ice, we identify recurring version by
		# the first character of the subscr_id
		if ( $this->isRecurringPayment() && str_starts_with( $this->row['PayPal Reference ID'] ?? '', 'I' ) ) {
			return 'paypal_ec';
		}

		if ( $this->isReversalType() ) {
			if ( !empty( $this->row['Invoice ID'] ) ) {
				return 'paypal_ec';
			}
		}
		return 'paypal';
	}

	protected function getOrderID(): string {
		foreach ( [ 'Invoice ID', 'Transaction Subject', 'Custom Field' ] as $field ) {
			$value = trim( (string)( $this->row[$field] ?? '' ) );
			if ( $value === '' ) {
				continue;
			}
			if ( preg_match( '/^[0-9]+(\.[0-9]+)?$/', $value ) === 1 ) {
				return $value;
			}
		}
		return '';
	}

	protected function getContributionTrackingId(): ?int {
		$parts = explode( '.', $this->getOrderID() );
		return $parts[0] ? (int)$parts[0] : null;
	}

	protected function getFeeAmount(): float {
		$fee = $this->row['Fee Amount'] ?? 0;
		if ( !$fee && isset( $this->feeRows[$this->row['Transaction ID']] ) ) {
			$fee = $this->feeRows[$this->row['Transaction ID']]['Gross Transaction Amount'];
		}
		if ( !$fee && isset( $this->feeRows[$this->row['Invoice ID']] ) ) {
			$fee = $this->feeRows[$this->row['Invoice ID']]['Gross Transaction Amount'];
		}
		if ( $fee ) {
			return $fee / 100;
		}
		return 0.0;
	}

	protected function getOriginalFeeAmount(): float {
		$fee = $this->getFeeAmount();
		if ( $this->row['Fee Debit or Credit'] === 'DR' || ( $this->feeRows[$this->row['Transaction ID']]['Transaction Debit or Credit'] ?? '' ) === 'DR' ) {
			return -$fee;
		}
		return $fee;
	}

	/**
	 * @return float
	 */
	protected function getOriginalNetAmount(): string {
		return (string)( $this->getOriginalTotalAmount() + $this->getOriginalFeeAmount() );
	}

	/**
	 * @return float
	 */
	protected function getOriginalTotalAmount(): string {
		$totalAmount = (float)( $this->row['Gross Transaction Amount'] ) / 100;
		if ( $this->row['Transaction Debit or Credit'] === 'DR' ) {
			$totalAmount = -$totalAmount;
		}
		return (string)$totalAmount;
	}

	/**
	 * @return float|int
	 */
	protected function getExchangeRate(): int|float {
		$exchangeRate = 1;
		if ( $this->hasConversion() ) {
			$conversion = $this->conversionRows[$this->row['Invoice ID']];
			$originalCurrency = $conversion[0];
			$convertedCurrency = $conversion[1];
			$exchangeRate = $convertedCurrency['Gross Transaction Amount'] / $originalCurrency['Gross Transaction Amount'];
		}
		return $exchangeRate;
	}

	/**
	 * @return mixed
	 */
	protected function getSettledCurrency(): mixed {
		if ( $this->hasConversion() ) {
			return $this->conversionRows[$this->row['Invoice ID']][1]['Gross Transaction Currency'];
		}
		return $this->row['Gross Transaction Currency'];
	}

	protected function getSettledTotalAmount(): string {
		if ( !$this->hasConversion() ) {
			return (string)$this->getOriginalTotalAmount();
		}
		return CurrencyRoundingHelper::round( $this->getOriginalTotalAmount() * $this->getExchangeRate(), $this->getSettledCurrency() );
	}

	protected function getSettledNetAmount(): string {
		if ( !$this->hasConversion() ) {
			return (string)( (float)$this->getSettledTotalAmount() + (float)$this->getSettledFeeAmount() );
		}
		return (string)( $this->conversionRows[$this->row['Invoice ID']][1]['Gross Transaction Amount'] / 100 );
	}

	protected function getSettledFeeAmount(): string {
		if ( !$this->hasConversion() ) {
			return (string)$this->getOriginalFeeAmount();
		}
		// Rely on the conversion being done in getTotalAmount for rounding consistency.
		return (string)( $this->getSettledNetAmount() - $this->getSettledTotalAmount() );
	}

	/**
	 * @return array
	 */
	protected function getGravyFields(): array {
		$gravyFields = [];
		if ( $this->isGravy() ) {
			$gravyFields['backend_processor_txn_id'] = $this->row['Transaction ID'];
			$gravyFields['backend_processor'] = $this->getGateway();
			$gravyFields['payment_orchestrator_reconciliation_id'] = $this->row['Custom Field'];
		}
		return $gravyFields;
	}

	/**
	 * @return array
	 */
	protected function getRecurringFields(): array {
		$recurringFields = [];
		if ( $this->isRecurringPayment() ) {
			$recurringFields['txn_type'] = 'subscr_payment';
			$recurringFields['subscr_id'] = $this->row['PayPal Reference ID'];
		}
		return $recurringFields;
	}

	/**
	 * @return array
	 * @throws \SmashPig\Core\UnhandledException
	 */
	protected function getReversalFields(): array {
		$reversalFields = [];
		if ( $this->isReversalType() ) {
			$reversalFields['type'] = $this->getTransactionType();
			$reversalFields['gateway_refund_id'] = $this->row['Transaction ID'];
			$reversalFields['gross_currency'] = $this->row['Gross Transaction Currency'];

			if ( ( $this->row['PayPal Reference ID Type'] ?? '' ) === 'TXN' ) {
				$reversalFields['gateway_parent_id'] = $this->row['PayPal Reference ID'];
			}
		} elseif ( $this->isReversalReversalType() ) {
			$reversalFields['type'] = $this->getTransactionType();
			$reversalFields['gateway_parent_id'] = $this->row['PayPal Reference ID'];

		} elseif ( $this->isReversalPrefix() ) {
			// Prefix says refund/chargeback, but code isn't one we handle -> skip (Python: "-Unknown (Refundish type)")
			throw new UnhandledException( 'Unhandled refundish transaction code: ' . $this->getTransactionCode() );
		}
		return $reversalFields;
	}

}
