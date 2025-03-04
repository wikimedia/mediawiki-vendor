<?php

namespace SmashPig\PaymentProviders\Adyen\Maintenance;

require __DIR__ . '/../../../Maintenance/MaintenanceBase.php';

use SmashPig\Core\DataFiles\HeadedCsvReader;
use SmashPig\Core\Logging\Logger;
use SmashPig\Maintenance\MaintenanceBase;
use SmashPig\PaymentProviders\PaymentProviderFactory;

/**
 * Batch refund settled Adyen payments. Required argument
 * is the path of a CSV file containing at least these three columns:
 * gateway_txn_id, amount, and currency. Amount should be specified in
 * major units (e.g. dollars) rather than minor units (e.g. cents).
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
		$this->desiredOptions['config-node']['default'] = 'adyen';
		$this->addArgument( 'file', 'CSV file containing payment parameters', true );
	}

	public function execute(): void {
		$filePath = $this->getArgument( 'file' );
		$reader = new HeadedCsvReader( $filePath );
		$headerList = implode( ',', $reader->headers() );
		Logger::info( "Opened CSV $filePath and found columns $headerList" );

		$required = [ 'gateway_txn_id', 'amount', 'currency' ];
		foreach ( $required as $columnName ) {
			if ( array_search( $columnName, $reader->headers() ) === false ) {
				throw new \RuntimeException(
					"CSV file $filePath does not contain a column called '$columnName'"
				);
			}
		}

		$provider = PaymentProviderFactory::getProviderForMethod( $this->getOption( 'method' ) );

		while ( $reader->valid() ) {
			$params = $reader->currentArray();
			// Our gateway_txn_id corresponds to Adyen's pspReference.
			$gatewayTxnId = $params['gateway_txn_id'];

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
