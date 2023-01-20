<?php

namespace SmashPig\PaymentProviders\dlocal\Maintenance;

require __DIR__ . '/../../../Maintenance/MaintenanceBase.php';

use SmashPig\Core\Context;
use SmashPig\Maintenance\MaintenanceBase;

/**
 * Successfully running this script should confirm the following:
 * - The API endpoint is reachable
 * - Our credentials work
 * - The healthcheck method is sending back a result
 *
 * Note: dLocal doesn't have a health check API method, so we're using 'getPaymentMethods' as a substitute.
 */
class HealthCheck extends MaintenanceBase {

	/**
	 * @var string
	 */
	public const HEALTHCHECK_METHOD = 'getPaymentMethods';

	/**
	 * @var string
	 */
	public const HEALTHCHECK_PARAM = 'MX';

	public function __construct() {
		parent::__construct();
		$this->desiredOptions['config-node']['default'] = 'dlocal';
	}

	public function execute(): void {
		$providerConfiguration = Context::get()->getProviderConfiguration();
		$api = $providerConfiguration->object( 'api' );
		$result = $api->{self::HEALTHCHECK_METHOD}( self::HEALTHCHECK_PARAM );
		print_r( $result );
	}
}

$maintClass = HealthCheck::class;

require RUN_MAINTENANCE_IF_MAIN;
