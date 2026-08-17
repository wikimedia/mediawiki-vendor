<?php

namespace SmashPig\PaymentProviders\Chariot\Maintenance;

use SmashPig\Core\Context;
use SmashPig\Maintenance\MaintenanceBase;
use SmashPig\PaymentProviders\Chariot\Api;

require __DIR__ . '/../../../Maintenance/MaintenanceBase.php';

class ListProperties extends MaintenanceBase {

	public function __construct() {
		parent::__construct();
		$this->addOption( 'resource-type', 'Optional resource type filter, e.g. deposit or donation', '' );
		$this->desiredOptions['config-node']['default'] = 'chariot';
	}

	public function execute(): void {
		Context::get()->getProviderConfiguration();

		$params = [];
		$resourceType = trim( (string)$this->getOption( 'resource-type' ) );
		if ( $resourceType !== '' ) {
			$params['resource_type'] = $resourceType;
		}

		$api = new Api();
		$result = $api->listProperties( $params );

		$json = json_encode( $result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES );
		if ( $json === false ) {
			throw new \RuntimeException( 'Unable to encode Chariot properties response as JSON' );
		}

		print $json . PHP_EOL;
	}
}

$maintClass = ListProperties::class;
require RUN_MAINTENANCE_IF_MAIN;
