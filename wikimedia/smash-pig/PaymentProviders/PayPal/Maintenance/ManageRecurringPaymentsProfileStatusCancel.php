<?php

namespace SmashPig\PaymentProviders\PayPal\Maintenance;

require __DIR__ . '/../../../Maintenance/MaintenanceBase.php';

use SmashPig\Core\Logging\Logger;
use SmashPig\Maintenance\MaintenanceBase;
use SmashPig\PaymentProviders\PaymentProviderFactory;

$maintClass = 'SmashPig\PaymentProviders\PayPal\Maintenance\ManageRecurringPaymentsProfileStatusCancel';

/**
 * Cancel one or more PayPal recurring subscriptions
 */
class ManageRecurringPaymentsProfileStatusCancel extends MaintenanceBase {

	public function __construct() {
		parent::__construct();
		$this->desiredOptions['config-node']['default'] = 'paypal';
		$this->addArgument( 'file', 'CSV file with a single column with PROFILEID (subscr_id) of subscriptions to cancel' );
	}

	/**
	 * Do the actual work of the script.
	 */
	public function execute() {
		$filename = $this->getArgument( 'file' );
		$file = fopen( $filename, 'r' );

		if ( !$file ) {
			throw new \RuntimeException( 'Could not find cancellation file: ' . $filename );
		}

		$provider = PaymentProviderFactory::getProviderForMethod( 'paypal' );

		while ( $cancel = fgetcsv( $file ) ) {
			if ( count( $cancel ) !== 1 ) {
				throw new \RuntimeException( 'Cancellation lines must have exactly 1 field, corresponding to subscr_id', true );
			}

			$subscr_id = $cancel[ 0 ];
			Logger::info( "Canceling subscription $subscr_id" );
			$result = $provider->cancelSubscription( [ 'subscr_id' => $subscr_id ] );

			if ( $result->isSuccessful() ) {
				Logger::info( "Canceled subscription $subscr_id" );
			} else {
				Logger::info( "Failed to cancel subscription $subscr_id" );
				foreach ( $result->getErrors() as $error ) {
					Logger::info( $error->getDebugMessage() );
				}
			}
		}

		fclose( $file );
	}
}

require RUN_MAINTENANCE_IF_MAIN;
