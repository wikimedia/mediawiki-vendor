<?php

namespace SmashPig\PaymentProviders\Braintree\Maintenance;

require __DIR__ . '/../../../Maintenance/MaintenanceBase.php';

use SmashPig\Core\Logging\Logger;
use SmashPig\Maintenance\MaintenanceBase;
use SmashPig\PaymentProviders\PaymentProviderFactory;

class GetReport extends MaintenanceBase {

	public function __construct() {
		parent::__construct();
		$this->desiredOptions['config-node']['default'] = 'braintree';
	}

	/**
	 * Do the actual work of the script.
	 */
	public function execute() {
		$provider = PaymentProviderFactory::getProviderForMethod( 'report' );
		$response = $provider->report();
		Logger::info( print_r( $response, true ) );
	}
}

$maintClass = GetReport::class;

require RUN_MAINTENANCE_IF_MAIN;
