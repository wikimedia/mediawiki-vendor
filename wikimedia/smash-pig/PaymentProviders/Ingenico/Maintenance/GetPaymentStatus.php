<?php

namespace SmashPig\PaymentProviders\Ingenico\Maintenance;

require 'IngenicoMaintenance.php';

use SmashPig\Core\Logging\Logger;

class GetPaymentStatus extends IngenicoMaintenance {

	public function __construct() {
		parent::__construct();
		$this->addOption( 'id', 'id of payment or refund to query', null, 'i' );
	}

	protected function runIngenicoScript() {
		$id = $this->getOption( 'id' );
		if ( empty( $id ) ) {
			$this->error( 'Need to specify an ID with -i or --id', true );
		}
		Logger::info( "Querying payment with ID $id" );
		$response = $this->provider->getPaymentStatus( $id );
		Logger::info( 'Response: ' );
		Logger::info( json_encode( $response, JSON_PRETTY_PRINT ) );
	}
}

$maintClass = GetPaymentStatus::class;

require RUN_MAINTENANCE_IF_MAIN;
