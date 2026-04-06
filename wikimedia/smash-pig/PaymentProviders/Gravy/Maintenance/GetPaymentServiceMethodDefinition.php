<?php

namespace SmashPig\PaymentProviders\Gravy\Maintenance;

require __DIR__ . '/../../../Maintenance/MaintenanceBase.php';

use SmashPig\Core\Context;
use SmashPig\Core\Logging\Logger;
use SmashPig\Maintenance\MaintenanceBase;

/**
 * Fetches the definitions of a method across multiple payment services connectors on Gravy
 * For example card would return the configurations on adyen, stripe, etc
 */
class GetPaymentServiceMethodDefinition extends MaintenanceBase {

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

		$result = $api->getPaymentServicesForMethod( [ 'method' => $method ] );
		print_r( json_encode( $result ) );
	}
}

$maintClass = GetPaymentServiceMethodDefinition::class;

require RUN_MAINTENANCE_IF_MAIN;
