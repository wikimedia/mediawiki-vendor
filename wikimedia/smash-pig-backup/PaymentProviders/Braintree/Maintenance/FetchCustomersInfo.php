<?php

namespace SmashPig\PaymentProviders\Braintree\Maintenance;

require __DIR__ . '/../../../Maintenance/MaintenanceBase.php';

use SmashPig\Core\Logging\Logger;
use SmashPig\Maintenance\MaintenanceBase;
use SmashPig\PaymentProviders\PaymentProviderFactory;

class FetchCustomersInfo extends MaintenanceBase {

	public function __construct() {
		parent::__construct();
		$this->desiredOptions['config-node']['default'] = 'braintree';
		// since function is shared for both venmo and paypal at paymentProvider, so it's ok to use venmo as default.
		$this->addOption( 'method', 'payment method to init, e.g. "venmo"', 'venmo', 'm' );
		$this->addArgument( 'file', ' CSV with the order_id, gateway_session_id' );
	}

	/**
	 * Do the actual work of the script.
	 */
	public function execute() {
		$filename = $this->getArgument( 'file' );
		$file = fopen( $filename, 'r' );

		if ( !$file ) {
			throw new \RuntimeException( 'Could not find fetch file: ' . $filename );
		}

		$provider = PaymentProviderFactory::getProviderForMethod( $this->getOption( 'method' ) );

		while ( $fetch = fgetcsv( $file ) ) {
			if ( count( $fetch ) !== 2 ) {
				throw new \RuntimeException( count( $fetch ) . ' fields, but fetch lines must have 2 fields: order_id, gateway_session_id', true );
			}
			$order_id = $fetch[ 0 ];
			Logger::info( "** Start fetch for order $order_id **" );
			/** @var $result \SmashPig\PaymentData\DonorDetails */
			$result = $provider->fetchCustomerData( $fetch[ 1 ] );
			if ( $result->getEmail() ) {
				Logger::info( "$order_id: fetched email " . $result->getEmail() );
			} else {
				Logger::info( "$order_id: Failed to fetch email!" );
			}
		}
		fclose( $file );
	}
}

$maintClass = FetchCustomersInfo::class;

require RUN_MAINTENANCE_IF_MAIN;
