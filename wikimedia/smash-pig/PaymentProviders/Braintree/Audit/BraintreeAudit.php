<?php namespace SmashPig\PaymentProviders\Braintree\Audit;

use SmashPig\Core\DataFiles\AuditParser;
use SmashPig\Core\Logging\Logger;
use SmashPig\Core\NormalizationException;
use SmashPig\Core\UtcDate;

class BraintreeAudit implements AuditParser {

	protected $fileData;

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

	protected function parseLine( $line ) {
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

	/**
	 * Is this a gravy Row.
	 *
	 */
	protected function isOrchestratorMerchantReference( array $row ): bool {
		$merchantReference = $row['contribution_tracking_id'];
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
