<?php

namespace SmashPig\PaymentProviders\Ingenico\Maintenance;

require 'IngenicoMaintenance.php';

use SmashPig\Core\ApiException;
use SmashPig\Core\DataFiles\HeadedCsvReader;
use SmashPig\Core\Logging\Logger;
use SmashPig\Core\Mapper\Transformers\AmountToCents;
use SmashPig\PaymentProviders\Ingenico\RefundStatus;

class RefundPayments extends IngenicoMaintenance {

	public function __construct() {
		parent::__construct();
		$this->addArgument( 'file', 'CSV file containing payment parameters', true );
	}

	/**
	 * Read a CSV and refund payments. CSV should have at least gateway_txn_id, amount,
	 * currency, and country columns. order_id, first_name and last_name are recommended.
	 */
	protected function runIngenicoScript() {
		$filePath = $this->getArgument( 'file' );
		$reader = new HeadedCsvReader( $filePath );
		$headerList = implode( ',', $reader->headers() );
		Logger::info( "Opened CSV $filePath and found columns $headerList" );
		$required = [ 'gateway_txn_id', 'amount', 'currency', 'country' ];
		foreach ( $required as $columnName ) {
			if ( array_search( $columnName, $reader->headers() ) === false ) {
				throw new \RuntimeException(
					"CSV file $filePath does not contain a column called '$columnName'"
				);
			}
		}
		while ( $reader->valid() ) {
			$params = $reader->currentArray();
			// Our gateway_txn_id corresponds to paymentId in Ingenico's documentation.
			$gatewayTxnId = $params['gateway_txn_id'];
			try {
				if ( !$this->isRefundable( $gatewayTxnId, $params ) ) {
					$reader->next();
					continue;
				}
				Logger::info( "Creating refund for payment $gatewayTxnId" );
				$createRefundResponse = $this->provider->createRefund( $gatewayTxnId, $params );
				$refundId = $createRefundResponse['id'];
				$refundStatus = $createRefundResponse['status'];
				Logger::info( "Refund $refundId is in status $refundStatus" );
				if ( $refundStatus === RefundStatus::PENDING_APPROVAL ) {
					Logger::info( "Approving refund $refundId for payment $gatewayTxnId" );
					$approveResponse = $this->provider->approveRefund( $refundId );
					if ( empty( $approveResponse ) ) {
						Logger::info( "Successfully approved refund $refundId for payment $gatewayTxnId" );
					} else {
						Logger::info(
							"Couldn't approve refund $refundId: " . json_encode( $approveResponse, JSON_PRETTY_PRINT )
						);
					}
				}
			} catch ( ApiException $ex ) {
				Logger::error( 'API error: ' . $ex->getMessage() );
			}
			$reader->next();
		}
	}

	protected function isRefundable( $gatewayTxnId, $params ) {
		Logger::info( "Getting status of payment $gatewayTxnId" );
		$statusResponse = $this->provider->getPaymentStatus( $gatewayTxnId );
		if ( !$statusResponse['statusOutput']['isRefundable'] ) {
			Logger::info( "Payment $gatewayTxnId is not refundable" );
			return false;
		}
		$statusAmount = $statusResponse['paymentOutput']['amountOfMoney'];
		$paramsAmount = [];
		$transformer = new AmountToCents();
		$transformer->transform( $params, $paramsAmount );
		if ( strval( $statusAmount['amount'] ) !== strval( $paramsAmount['amount'] ) ) {
			Logger::info(
				"Amount from file {$paramsAmount['amount']} " .
				"doesn't match payment amount {$statusAmount['amount']}"
			);
			return false;
		}
		if ( $statusAmount['currencyCode'] !== $params['currency'] ) {
			Logger::info(
				"Currency from file {$params['currency']} " .
				"doesn't match payment currency {$statusAmount['currencyCode']}"
			);
			return false;
		}
		return true;
	}
}

$maintClass = RefundPayments::class;

require RUN_MAINTENANCE_IF_MAIN;
