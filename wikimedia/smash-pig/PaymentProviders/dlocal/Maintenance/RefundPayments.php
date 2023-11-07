<?php

namespace SmashPig\PaymentProviders\dlocal\Maintenance;

require __DIR__ . '/../../../Maintenance/MaintenanceBase.php';

use SmashPig\Core\DataFiles\HeadedCsvReader;
use SmashPig\Core\Logging\Logger;
use SmashPig\Maintenance\MaintenanceBase;
use SmashPig\PaymentProviders\PaymentProviderFactory;

/**
 * Batch refund settled Dlocal payments. Required argument
 * is the path of a CSV file containing the gateway_txn_id
 *
 * The final status of the refund comes in on an REFUND IPN.
 * Refunding the same payment multiple times will not error on this script
 * but will send back an IPN message with: Already fully refunded,
 * no balance available for new requested refund
 */
class RefundPayments extends MaintenanceBase {

	public function __construct() {
		parent::__construct();
		$this->addOption( 'method', 'payment method to instantiate, e.g. "cc"', 'cc', 'm' );
		$this->desiredOptions['config-node']['default'] = 'dlocal';
		$this->addArgument( 'file', 'CSV file containing payment parameters', true );
	}

	public function execute() {
		$filePath = $this->getArgument( 'file' );
		$reader = new HeadedCsvReader( $filePath );
		$headerList = implode( ',', $reader->headers() );
		Logger::info( "Opened CSV $filePath and found columns $headerList" );

		$required = [ 'gateway_txn_id' ];
		foreach ( $required as $columnName ) {
			if ( !in_array( $columnName, $reader->headers() ) ) {
				throw new \RuntimeException(
					"CSV file $filePath does not contain a column called '$columnName'"
				);
			}
		}

		$provider = PaymentProviderFactory::getProviderForMethod( $this->getOption( 'method' ) );

		while ( $reader->valid() ) {
			$params = $reader->currentArray();
			// Our gateway_txn_id corresponds to dlocals payment_id
			$gatewayTxnId = $params['gateway_txn_id'];
			$params['payment_id'] = $params['gateway_txn_id'];
			unset( $params['gateway_txn_id'] );

			try {
				$result = $provider->refundPayment( $params );
				if ( $result->isSuccessful() ) {
					Logger::info( "Refunded payment $gatewayTxnId" );
				} else {
					Logger::info( "Could not refund payment $gatewayTxnId" );
					Logger::info( 'Full response: ' . json_encode( $result->getRawResponse() ) );
				}
			}
			catch ( \Exception $ex ) {
				Logger::error( "Could not refund payment $gatewayTxnId", null, $ex );
			}
			$reader->next();
		}
	}
}

$maintClass = RefundPayments::class;

require RUN_MAINTENANCE_IF_MAIN;
