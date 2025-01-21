<?php

namespace SmashPig\PaymentProviders\Ingenico\Maintenance;

require __DIR__ . '/../../../Maintenance/MaintenanceBase.php';

use SmashPig\Maintenance\MaintenanceBase;
use SmashPig\PaymentProviders\PaymentProviderFactory;

/**
 * Send queries to the Ingenico Connect API and display responses.
 */
abstract class IngenicoMaintenance extends MaintenanceBase {
	/**
	 * @var PaymentProvider
	 */
	protected $provider;

	public function __construct() {
		parent::__construct();
		$this->addOption( 'method', 'payment method to instatiate, e.g. "cc"', 'cc', 'm' );
		$this->desiredOptions['config-node']['default'] = 'ingenico';
	}

	public function execute() {
		$method = $this->getOption( 'method' );
		$this->provider = PaymentProviderFactory::getProviderForMethod( $method );
		$this->runIngenicoScript();
	}

	/**
	 * Do the actual work of the script.
	 */
	abstract protected function runIngenicoScript();
}
