<?php

namespace SmashPig\PaymentProviders\Gravy\Maintenance;

require __DIR__ . '/../../../Maintenance/MaintenanceBase.php';

use SmashPig\Core\Context;
use SmashPig\Core\Logging\Logger;
use SmashPig\Maintenance\MaintenanceBase;

/**
 * Fetch Payment service definition details
 * for gravy payment method
 */
class ListPaymentServiceDefinitions extends MaintenanceBase {

	public function __construct() {
		parent::__construct();
		$this->desiredOptions['config-node']['default'] = 'gravy';
		$this->addArgument( 'method', 'Gravy payment method to query', false );
	}

	public function execute(): void {
		$method = $this->getArgument( 'method' );
		Logger::info( "Querying Gravy for details on payment method $method" );

		$providerConfiguration = Context::get()->getProviderConfiguration();
		$api = $providerConfiguration->object( 'api' );

		$result = $api->getPaymentServiceDefinition( $method );
		print_r( json_encode( $result ) );
	}
}

$maintClass = ListPaymentServiceDefinitions::class;

require RUN_MAINTENANCE_IF_MAIN;
