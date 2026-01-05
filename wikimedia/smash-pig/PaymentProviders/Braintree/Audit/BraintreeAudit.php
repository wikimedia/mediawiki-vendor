<?php namespace SmashPig\PaymentProviders\Braintree\Audit;

use Brick\Money\Money;
use SmashPig\Core\DataFiles\AuditParser;
use SmashPig\Core\Logging\Logger;
use SmashPig\Core\NormalizationException;
use SmashPig\Core\UtcDate;

class BraintreeAudit implements AuditParser {

	protected $fileData;

	protected array $totals = [];
	/**
	 * @var array
	 */
	private array $ignoredDisputeStatuses;

	public function parseFile( string $path ): array {
		$this->fileData = [];
		$file = json_decode( file_get_contents( $path, 'r' ), true );

		foreach ( $file as $line ) {
			try {
				$this->parseLine( $line );
			} catch ( NormalizationException $ex ) {
				Logger::error( $ex->getMessage() );
			}
		}

		return $this->fileData;
	}

	protected function parseLine( $line ): void {
		// Is this a raw sql file - this won't actually do disputes yet so better
		// not create them until it does.
		$isRaw = is_array( $line['amount'] ?? null ) || is_array( $line['statusHistory'] ?? null );
		if ( $isRaw ) {
			$isDispute = is_array( $line['amountWon'] ?? null );
			if ( $isDispute ) {
				// As far as we know the only interesting one is 'WON' but tracking what we see while ignoring
				if ( $line['status'] !== 'WON' ) {
					if ( !isset( $this->ignoredDisputeStatuses[$line['status']] ) ) {
						$this->ignoredDisputeStatuses[$line['status']] = 0;
					}
					$this->ignoredDisputeStatuses[$line['status']]++;
					return;
				}
				$this->fileData[] = $this->getMessageFromRawDispute( $line );
				return;
			}
			$isRefund = is_array( $line['refundedTransaction'] ?? null );
			if ( $isRefund ) {
				$this->fileData[] = $this->getMessageFromRawRefund( $line );
				return;
			}
			$this->fileData[] = $this->getMessageFromRaw( $line );
			return;
		}
		// This is the legacy processing - we are moving towards the raw processing.
		$row = $line;
		$msg = [];
		// Common to all types, since we normalized already from the Maintenance Script SearchTransactions
		$msg['date'] = UtcDate::getUtcTimestamp( $row['date'] );
		$msg['gateway'] = $msg['audit_file_gateway'] = 'braintree';
		if ( $this->isOrchestratorMerchantReference( $row ) ) {
			$msg['payment_orchestrator_reconciliation_id'] = $row['invoice_id'];
			$msg['backend_processor'] = $msg['gateway'];
			$msg['backend_processor_txn_id'] = $row['gateway_txn_id'];
			$msg['gateway'] = 'gravy';
		} else {
			$msg['contribution_tracking_id'] = $row['contribution_tracking_id'];
		}
		$msg['invoice_id'] = $row['invoice_id'];
		$msg['payment_method'] = $row['payment_method'];
		$msg['gross'] = $row['gross'];
		$msg['currency'] = $row['currency'];
		$msg['email'] = $row['email'];
		$msg['phone'] = $row['phone'];
		$msg['first_name'] = $row['first_name'];
		$msg['last_name'] = $row['last_name'];

		if ( !isset( $row['type'] ) ) {
			// always status as 'SETTLED', so no need to filter the status
			$this->parseDonation( $row, $msg );
		} else {
			$msg['type'] = $row['type'];
			if ( $row['type'] === 'refund' ) {
				$this->parseRefund( $row, $msg );
			} else {
				$this->parseDispute( $row, $msg );
			}
		}
		$this->fileData[] = $msg;
	}

	private function getMessageFromRaw( array $row ): array {
		$msg = [];
		// Common to all types, since we normalized already from the Maintenance Script SearchTransactions
		$msg['date'] = UtcDate::getUtcTimestamp( $row['createdAt'] );
		$msg['gateway'] = $msg['audit_file_gateway'] = 'braintree';
		$msg['invoice_id'] = $row['orderId'];
		if ( $this->isOrchestratorMerchantReference( $row ) ) {
			$msg['payment_orchestrator_reconciliation_id'] = $row['orderId'];
			$msg['backend_processor'] = 'braintree';
			$msg['backend_processor_txn_id'] = $row['id'];
			$msg['gateway'] = 'gravy';
		} else {
			$orderParts = explode( '.', $msg['invoice_id'] );
			$msg['contribution_tracking_id'] = $orderParts[0];
		}
		$msg['payment_method'] = isset( $row['paymentMethodSnapshot']['payer'] ) ? 'paypal' : 'venmo';
		$msg['gross'] = $msg['original_total_amount'] = $row['amount']['value'];
		$msg['currency'] = $msg['original_currency'] = $row['amount']['currencyCode'];
		$msg['email'] = $this->getPayerInfo( $row, 'email' );
		$msg['phone'] = $this->getPayerInfo( $row, 'phone' );
		$msg['first_name'] = $this->getPayerInfo( $row, 'first_name' );
		$msg['last_name'] = $this->getPayerInfo( $row, 'last_name' );
		$msg['external_identifier'] = $this->getPayerInfo( $row, 'username' );
		$msg['gateway_txn_id'] = $row['id'];
		$msg['settled_date'] = UtcDate::getUtcTimestamp( $row['disbursementDetails']['date'] );
		$msg['settlement_batch_reference'] = str_replace( '-', '', $row['disbursementDetails']['date'] );
		$msg['settled_total_amount'] = $msg['settled_net_amount'] = $row['disbursementDetails']['amount']['value'];
		$msg['settled_fee_amount'] = 0;
		$msg['exchange_rate'] = $row['disbursementDetails']['exchangeRate'];
		$msg['settled_currency'] = $row['disbursementDetails']['amount']['currencyCode'];

		if ( !isset( $this->totals[$msg['settled_date']] ) ) {
			$this->totals[$msg['settled_date']] = Money::zero( $msg['currency'] );
		}
		$this->totals[$msg['settled_date']] = $this->totals[$msg['settled_date']]->plus( $msg['settled_net_amount'] );
		return $msg;
	}

	private function getMessageFromRawDispute( array $row ): array {
		$msg = [];
		$msg['gateway'] = $msg['audit_file_gateway'] = 'braintree';
		$msg['type'] = 'chargeback';
		foreach ( $row['statusHistory'] as $history ) {
			if ( $history['status'] === 'WON' ) {
				// Using the date it was won both here & settled date, arguably should use initiated date here
				// and this for settled date?
				$msg['date'] = UtcDate::getUtcTimestamp( $history['effectiveDate'] );
			}
		}

		$parentTransaction = $row['transaction'];
		$msg['invoice_id'] = $parentTransaction['orderId'];
		if ( $this->isOrchestratorMerchantReference( $parentTransaction ) ) {
			$msg['backend_processor'] = 'braintree';
			$msg['backend_processor_parent_id'] = $parentTransaction['id'];
			$msg['backend_processor_refund_id'] = $row['id'];
			$msg['gateway'] = 'gravy';
		} else {
			$orderParts = explode( '.', $msg['invoice_id'] );
			$msg['contribution_tracking_id'] = $orderParts[0];
			$msg['gateway_parent_id'] = $parentTransaction['id'];
			$msg['gateway_refund_id'] = $row['id'];
		}
		$msg['payment_method'] = isset( $row['paymentMethodSnapshot']['payer'] ) ? 'paypal' : 'venmo';
		$msg['gross'] = $row['amountWon']['value'];
		$msg['currency'] = $msg['original_currency'] = $row['amountWon']['currencyCode'];
		$msg['email'] = $this->getPayerInfo( $parentTransaction, 'email' );
		$msg['phone'] = $this->getPayerInfo( $parentTransaction, 'phone' );
		$msg['first_name'] = $this->getPayerInfo( $parentTransaction, 'first_name' );
		$msg['last_name'] = $this->getPayerInfo( $parentTransaction, 'last_name' );
		$msg['external_identifier'] = $this->getPayerInfo( $parentTransaction, 'username' );
		$msg['settled_date'] = $msg['date'];
		$msg['settlement_batch_reference'] = gmdate( 'Ymd', $msg['date'] );
		$msg['settled_total_amount'] = $msg['settled_net_amount'] = $msg['original_total_amount'] = -$row['amountWon']['value'];
		$msg['settled_fee_amount'] = 0;
		$msg['exchange_rate'] = 1;
		$msg['settled_currency'] = $row['amountWon']['currencyCode'];

		if ( !isset( $this->totals[$msg['settled_date']] ) ) {
			$this->totals[$msg['settled_date']] = Money::zero( $msg['currency'] );
		}
		$this->totals[$msg['settled_date']] = $this->totals[$msg['settled_date']]->plus( $msg['settled_net_amount'] );
		return $msg;
	}

	private function getMessageFromRawRefund( array $row ): array {
		$msg = [];
		$msg['gateway'] = $msg['audit_file_gateway'] = 'braintree';
		$msg['type'] = 'refund';
		$msg['date'] = UtcDate::getUtcTimestamp( $row['createdAt'] );

		$parentTransaction = $row['refundedTransaction'];
		$msg['invoice_id'] = $parentTransaction['orderId'];
		if ( $this->isOrchestratorMerchantReference( $parentTransaction ) ) {
			$msg['backend_processor'] = 'braintree';
			$msg['backend_processor_parent_id'] = $parentTransaction['id'];
			$msg['backend_processor_refund_id'] = $row['id'];
			$msg['gateway'] = 'gravy';
		} else {
			$orderParts = explode( '.', $msg['invoice_id'] );
			$msg['contribution_tracking_id'] = $orderParts[0];
			$msg['gateway_parent_id'] = $parentTransaction['id'];
			$msg['gateway_refund_id'] = $row['id'];
		}
		$msg['payment_method'] = isset( $row['paymentMethodSnapshot']['payer'] ) ? 'paypal' : 'venmo';
		$msg['gross'] = $row['amount']['value'];
		$msg['currency'] = $msg['original_currency'] = $row['amount']['currencyCode'];
		$msg['email'] = $this->getPayerInfo( $row, 'email' );
		$msg['phone'] = $this->getPayerInfo( $row, 'phone' );
		$msg['first_name'] = $this->getPayerInfo( $row, 'first_name' );
		$msg['last_name'] = $this->getPayerInfo( $row, 'last_name' );
		$msg['external_identifier'] = $this->getPayerInfo( $row, 'username' );
		$msg['settled_date'] = UtcDate::getUtcTimestamp( $row['disbursementDetails']['date'] );
		$msg['settlement_batch_reference'] = str_replace( '-', '', $row['disbursementDetails']['date'] );
		$msg['original_total_amount'] = -$row['amount']['value'];
		$msg['settled_total_amount'] = $msg['settled_net_amount'] = $row['disbursementDetails']['amount']['value'];
		$msg['settled_fee_amount'] = 0;
		$msg['exchange_rate'] = $row['disbursementDetails']['exchangeRate'];
		$msg['settled_currency'] = $row['disbursementDetails']['amount']['currencyCode'];

		if ( !isset( $this->totals[$msg['settled_date']] ) ) {
			$this->totals[$msg['settled_date']] = Money::zero( $msg['currency'] );
		}
		$this->totals[$msg['settled_date']] = $this->totals[$msg['settled_date']]->plus( $msg['settled_net_amount'] );
		return $msg;
	}

	/**
	 * @param array $row
	 * @param string $fieldName
	 * @param bool $isChargeBack
	 * @return string|null
	 */
	private function getPayerInfo( array $row, string $fieldName, bool $isChargeBack = false ): ?string {
		if ( !$isChargeBack && $row['paymentMethodSnapshot'] ) {
			$payerBlock = $row['paymentMethodSnapshot'];
		} elseif ( $isChargeBack && $row['transaction']['paymentMethodSnapshot'] ) {
			$payerBlock = $row['transaction']['paymentMethodSnapshot'];
		} else {
			return null;
		}
		if ( !empty( $payerBlock ) && isset( $payerBlock['payer'] ) ) {
			return $payerBlock['payer'][$fieldName];
		}
		if ( isset( $payerBlock[$fieldName] ) ) {
			return $payerBlock[$fieldName];
		}
		return null;
	}

	/**
	 * Is this a gravy Row.
	 *
	 */
	protected function isOrchestratorMerchantReference( array $row ): bool {
		$merchantReference = $row['contribution_tracking_id'] ?? $row['orderId'];
		// ignore gravy transactions, they have no period and contain letters
		return ( !strpos( $merchantReference, '.' ) && !is_numeric( $merchantReference ) );
	}

	protected function parseRefund( array $row, array &$msg ) {
		$msg['gateway_parent_id'] = $row['gateway_parent_id'];
		$msg['gateway_refund_id'] = $row['gateway_refund_id'];
	}

	protected function parseDispute( array $row, array &$msg ) {
		$msg['gateway_txn_id'] = $row['gateway_txn_id'];
	}

	protected function parseDonation( array $row, array &$msg ) {
		$msg['gateway_txn_id'] = $row['gateway_txn_id'];
	}
}
