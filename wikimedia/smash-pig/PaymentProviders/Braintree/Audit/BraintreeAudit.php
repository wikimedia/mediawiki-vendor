<?php namespace SmashPig\PaymentProviders\Braintree\Audit;

use Brick\Money\Money;
use SmashPig\Core\DataFiles\AuditParser;
use SmashPig\Core\Helpers\Base62Helper;
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
		if ( $file && !isset( $file['id'] ) && !isset( $file['type'] ) ) {
			// File is in the old format where the json is valid for the whole file.
			// This format is harder to grep and has a higher risk of invalid json if it crashes
			// while writing.
			// @todo eliminate after transition period.
			foreach ( $file as $line ) {
				try {
					$this->parseLine( $line );
				} catch ( NormalizationException $ex ) {
					Logger::error( $ex->getMessage() );
				}
			}
			return $this->fileData;

		}
		foreach ( file( $path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES ) as $line ) {
			// File is in new NDJSON format - each line is a valid json object.
			$item = json_decode( $line, true );
			try {
				if ( !is_array( $item ) ) {
					throw new NormalizationException( 'Invalid Item ' . $line );
				}
				$this->parseLine( $item );
			} catch ( NormalizationException $ex ) {
				Logger::error( $ex->getMessage() );
			}
		}
		$result = $this->fileData;
		if ( str_contains( $path, '_disbursement_' ) ) {
			foreach ( $this->totals as $batchName => $total ) {
				// Add batch aggregate row - we have split these into 2 batches - chargebacks
				// and donations + refunds because that aligns with the Disbursement search UI
				// and chargebacks seem a bit unpredictable since we can only query by 'effective date'
				// and hope the disbursement date is reliable from there. Better to keep what is known
				// reliable to be reliable.
				$result[] = $this->getAggregateRow( $batchName, $total );
			}
		}
		return $result;
	}

	private function getAggregateRow( string $batchName, Money $total ): array {
		return [
			'settled_currency' => (string)$total->getCurrency(),
			'settled_total_amount' => (string)$total->getAmount(),
			'gateway' => 'braintree',
			'type' => 'payout',
			'gateway_txn_id' => $batchName,
			'invoice_id' => $batchName,
			'settlement_batch_reference' => $batchName,
			'settled_date' => substr( $batchName, 0, 8 ),
			'date' => substr( $batchName, 0, 8 ),
		];
	}

	protected function parseLine( $line ): void {
		// Is this a raw sql file - this won't actually do disputes yet so better
		// not create them until it does.
		$isRaw = is_array( $line['amount'] ?? null ) || is_array( $line['statusHistory'] ?? null );
		if ( $isRaw ) {
			$isDispute = is_array( $line['amountDisputed'] ?? null );
			if ( $isDispute ) {
				// As far as we know the only interesting one is 'LOST' but tracking what we see while ignoring
				if ( $line['status'] !== 'LOST' ) {
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
		$msg['full_name'] = $this->getPayerInfo( $row, 'fullname' );
		$msg['external_identifier'] = $this->getPayerInfo( $row, 'username' );
		$msg['gateway_txn_id'] = $row['id'];
		$msg['settled_date'] = UtcDate::getUtcTimestamp( $row['disbursementDetails']['date'] );
		$msg['settlement_batch_reference'] = str_replace( '-', '', $row['disbursementDetails']['date'] );
		$msg['settled_total_amount'] = $msg['settled_net_amount'] = $row['disbursementDetails']['amount']['value'];
		$msg['settled_fee_amount'] = 0;
		$msg['exchange_rate'] = $row['disbursementDetails']['exchangeRate'];
		$msg['settled_currency'] = $row['disbursementDetails']['amount']['currencyCode'];

		if ( !isset( $this->totals[$msg['settlement_batch_reference']] ) ) {
			$this->totals[$msg['settlement_batch_reference']] = Money::zero( $msg['currency'] );
		}
		$this->totals[$msg['settlement_batch_reference']] = $this->totals[$msg['settlement_batch_reference']]->plus( $msg['settled_net_amount'] );
		return $msg;
	}

	private function getMessageFromRawDispute( array $row ): array {
		$msg = [];
		$msg['gateway'] = $msg['audit_file_gateway'] = 'braintree';
		$msg['type'] = 'chargeback';
		foreach ( $row['statusHistory'] as $history ) {
			if ( $history['status'] === 'LOST' ) {
				// Using the date it was won both here & settled date, arguably should use initiated date here
				// and this for settled date?
				$msg['date'] = UtcDate::getUtcTimestamp( $history['effectiveDate'] );
			}
			if ( !empty( $history['disbursementDate'] ) ) {
				$msg['settled_date'] = UtcDate::getUtcTimestamp( $history['disbursementDate'] );
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
		$msg['gross'] = $row['amountDisputed']['value'];
		$msg['currency'] = $msg['original_currency'] = $row['amountDisputed']['currencyCode'];
		$msg['email'] = $this->getPayerInfo( $parentTransaction, 'email' );
		$msg['phone'] = $this->getPayerInfo( $parentTransaction, 'phone' );
		$msg['first_name'] = $this->getPayerInfo( $parentTransaction, 'first_name' );
		$msg['last_name'] = $this->getPayerInfo( $parentTransaction, 'last_name' );
		$msg['external_identifier'] = $this->getPayerInfo( $parentTransaction, 'username' );
		$msg['settled_total_amount'] = $msg['settled_net_amount'] = $msg['original_total_amount'] = -$row['amountDisputed']['value'];
		$msg['settled_fee_amount'] = 0;
		$msg['exchange_rate'] = 1;
		$msg['settled_currency'] = $row['amountDisputed']['currencyCode'];

		if ( !empty( $msg['settled_date'] ) ) {
			$msg['settlement_batch_reference'] = gmdate( 'Ymd', $msg['settled_date'] ) . '_chargebacks';
		}
		if ( !isset( $this->totals[$msg['settlement_batch_reference']] ) ) {
			$this->totals[$msg['settlement_batch_reference']] = Money::zero( $msg['currency'] );
		}
		$this->totals[$msg['settlement_batch_reference']] = $this->totals[$msg['settlement_batch_reference']]->plus( $msg['settled_net_amount'] );
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
			$msg['gateway_parent_id'] = Base62Helper::toUuid( $row['orderId'] );
			// We don't get the gravy refund ID at the moment and we have to have something so use the braintree one.
			$msg['gateway_refund_id'] = $row['id'];
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

		if ( !isset( $this->totals[$msg['settlement_batch_reference']] ) ) {
			$this->totals[$msg['settlement_batch_reference']] = Money::zero( $msg['currency'] );
		}
		$this->totals[$msg['settlement_batch_reference']] = $this->totals[$msg['settlement_batch_reference']]->plus( $msg['settled_net_amount'] );
		return $msg;
	}

	/**
	 * @param array $row
	 * @param string $fieldName
	 * @param bool $isChargeBack
	 * @return string|null
	 */
	private function getPayerInfo( array $row, string $fieldName, bool $isChargeBack = false ): ?string {
		$customFieldValue = $this->getCustomFieldValue( $row, $fieldName );
		if ( $customFieldValue ) {
			return $customFieldValue;
		}
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

	protected function getCustomFieldValue( array $row, string $fieldName ): ?string {
		if ( !isset( $row['customFields'] ) ) {
			return null;
		}
		foreach ( $row['customFields'] as $customField ) {
			if ( $customField['name'] === $fieldName ) {
				return $customField['value'];
			}
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
