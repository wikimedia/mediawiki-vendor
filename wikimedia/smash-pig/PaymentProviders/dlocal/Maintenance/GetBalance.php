<?php

namespace SmashPig\PaymentProviders\dlocal\Maintenance;

require __DIR__ . '/../../../Maintenance/MaintenanceBase.php';

use SmashPig\Core\Context;
use SmashPig\Maintenance\MaintenanceBase;

/**
 * Get the current balance
 */
class GetBalance extends MaintenanceBase {

	public function __construct() {
		parent::__construct();
		$this->desiredOptions['config-node']['default'] = 'dlocal';
	}

	public function execute(): void {
		$providerConfiguration = Context::get()->getProviderConfiguration();
		$api = $providerConfiguration->object( 'api' );
		$result = $api->getBalance();
		print_r( $result );
	}
}

$maintClass = GetBalance::class;

require RUN_MAINTENANCE_IF_MAIN;
