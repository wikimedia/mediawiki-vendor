<?php

namespace SmashPig\PaymentProviders\PayPal\Maintenance;

require __DIR__ . '/../../../Maintenance/MaintenanceBase.php';

use SmashPig\Core\Logging\Logger;
use SmashPig\Maintenance\MaintenanceBase;
use SmashPig\PaymentProviders\PaymentProviderFactory;

$maintClass = 'SmashPig\PaymentProviders\PayPal\Maintenance\RefundPayments';

/**
 * Refund one or more PayPal transactions
 */
class RefundPayments extends MaintenanceBase {

	public function __construct() {
		parent::__construct();
		$this->desiredOptions['config-node']['default'] = 'paypal';
		$this->addArgument( 'file', ' CSV with the order_id, gateway_txn_id, amount if partial refund, of the donations you want to refund)' );
	}

	/**
	 * Do the actual work of the script.
	 */
	public function execute() {
		$filename = $this->getArgument( 'file' );
		$file = fopen( $filename, 'r' );

		if ( !$file ) {
			throw new \RuntimeException( 'Could not find refund file: ' . $filename );
		}

		$provider = PaymentProviderFactory::getProviderForMethod( 'paypal' );

		while ( $refund = fgetcsv( $file ) ) {
			if ( count( $refund ) < 2 || count( $refund ) > 3 ) {
				throw new \RuntimeException( count( $refund ) . ' fields, but refund lines must have at least 2 fields: order_id, gateway_txn_id, if partial, then need additional amount field', true );
			}

			$order_id = $refund[ 0 ];
			$gateway_txn_id = $refund[ 1 ];

			Logger::info( "Refund payment for $order_id" );
			$params = [
				'order_id' => $order_id,
				'gateway_txn_id' => $gateway_txn_id,
			];
			if ( count( $refund ) === 3 ) {
				$amount = $refund[ 2 ];
				$params['amount'] = $amount;
			}
			$result = $provider->refundPayment( $params );

			if ( $result->isSuccessful() ) {
				Logger::info( "Refunded payment $gateway_txn_id" );
			} else {
				Logger::info( "Failed to refund payment $gateway_txn_id" );
				foreach ( $result->getErrors() as $error ) {
					Logger::info( $error->getDebugMessage() );
				}
			}
		}

		fclose( $file );
	}
}

require RUN_MAINTENANCE_IF_MAIN;
