<?php
declare( strict_types=1 );

namespace SmashPig\PaymentProviders\PayPal\Audit;

use SmashPig\Core\Helpers\Base62Helper;
use SmashPig\Core\IgnoredException;
use SmashPig\Core\UnhandledException;

/**
 * Parser for TRR files.
 *
 * Handles Transaction Detail Report. (TRR) from PayPal.
 *
 * Rows provide details on transactions.
 *
 * @see https://developer.paypal.com/docs/reports/sftp-reports/transaction-detail
 */
class TRRFileParser extends BaseParser {

	/**
	 * @throws UnhandledException|\SmashPig\Core\IgnoredException
	 */
	public function getMessage(): array {
		if ( $this->row['Transactional Status'] !== 'S' ) {
			// Skip transaction, not settled.
			throw new IgnoredException( 'Transaction status skipped: ' . $this->row['Transactional Status'] );
		}
		if ( $this->isBraintreePayment() ) {
			throw new IgnoredException( 'Braintree transaction skipped' );
		}
		if ( $this->isDebitPaymentToSomeoneElse() ) {
			throw new UnhandledException( 'Debit payment skipped' );
		}
		if ( !empty( $this->row['Billing Address Line1'] ) ) {
			$addr_prefix = 'Billing Address ';
		} else {
			$addr_prefix = 'Shipping Address ';
		}
		// Note that the python script sets no thank you to 'Audit configured not to send messages'
		// I have not retained that as it does not seem like a decision for this low in the stack.
		$isGravy = $this->isGravy();
		$msg = [
			'gateway_txn_id' => $isGravy ? Base62Helper::toUuid( $this->row['Custom Field'] ) : $this->row['Transaction ID'],
			'gateway' => $isGravy ? 'gravy' : $this->getGateway(),
			'audit_file_gateway' => 'paypal',
			'date' => strtotime( $this->row['Transaction Initiation Date'] ),
			'settled_date' => strtotime( $this->row['Transaction Completion Date'] ),
			'settlement_batch_reference' => str_replace( '/', '', substr( $this->row['Transaction Completion Date'], 0, 10 ) ),
			'settled_total_amount' => $this->getSettledTotalAmount(),
			'settled_net_amount' => $this->getSettledNetAmount(),
			'settled_fee_amount' => $this->getSettledFeeAmount(),
			'exchange_rate' => $this->getExchangeRate(),
			'settled_currency' => $this->getSettledCurrency(),
			'gross' => ( (float)$this->row['Gross Transaction Amount'] ) / 100,
			'currency' => $this->row['Gross Transaction Currency'],
			'original_net_amount' => $this->getOriginalNetAmount(),
			'original_fee_amount' => $this->getOriginalFeeAmount(),
			'fee' => $this->getFeeAmount(),
			'gateway_status' => $this->row['Transactional Status'],
			'email' => $this->row["Payer's Account ID"],
			'payment_method' => 'paypal',
			'street_address' => $this->row[$addr_prefix . 'Line1'],
			'supplemental_address_1' => $this->row[$addr_prefix . 'Line2'],
			'city' => $this->row[$addr_prefix . 'City'],
			'state_province' => $this->row[$addr_prefix . 'State'],
			'postal_code' => $this->row[$addr_prefix . 'Zip'],
			'country' => $this->row[$addr_prefix . 'Country'],
			'last_name' => $this->row['Last Name'] ?? null,
			'first_name' => $this->row['First Name'] ?? null,
			'payment_submethod' => $this->row['Card Type'] ?? null,
			'order_id' => $this->getOrderID(),
			'contribution_tracking_id' => $this->getContributionTrackingId(),
		];

		return $msg + $this->getGravyFields() + $this->getRecurringFields() + $this->getReversalFields();
	}

}
