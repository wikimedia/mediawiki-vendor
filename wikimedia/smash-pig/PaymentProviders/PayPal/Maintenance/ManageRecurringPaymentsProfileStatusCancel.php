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
		$this->addOption(
			'notefile',
			'Text file whose contents will be sent as a note along with the cancellation',
			null,
			'n'
		);
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

		$params = [];
		$noteFile = $this->getOption( 'notefile' );
		if ( $noteFile && file_exists( $noteFile ) ) {
			$params['note'] = trim( file_get_contents( $noteFile ) );
			$noteLength = mb_strlen( $params['note'] );
			if ( $noteLength > 128 ) {
				throw new \RuntimeException( "Note length of $noteLength characters is greater than the maximum 128." );
			}
		}

		$provider = PaymentProviderFactory::getProviderForMethod( 'paypal' );

		while ( $cancel = fgetcsv( $file ) ) {
			if ( count( $cancel ) !== 1 ) {
				throw new \RuntimeException( 'Cancellation lines must have exactly 1 field, corresponding to subscr_id', true );
			}

			$subscr_id = $cancel[ 0 ];
			Logger::info( "Canceling subscription $subscr_id" );
			$params[ 'subscr_id' ] = $subscr_id;
			$result = $provider->cancelSubscription( $params );

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
