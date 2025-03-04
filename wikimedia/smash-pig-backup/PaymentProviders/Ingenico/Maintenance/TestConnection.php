<?php

namespace SmashPig\PaymentProviders\Ingenico\Maintenance;

use SmashPig\Core\Logging\Logger;

require 'IngenicoMaintenance.php';

class TestConnection extends IngenicoMaintenance {

	public function __construct() {
		parent::__construct();
		$this->desiredOptions['method']['default'] = 'test';
	}

	/**
	 * Do the actual work of the script.
	 */
	protected function runIngenicoScript() {
		$response = $this->provider->testConnection();
		Logger::info( print_r( $response, true ) );
	}
}

$maintClass = TestConnection::class;

require RUN_MAINTENANCE_IF_MAIN;
