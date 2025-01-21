<?php

namespace SmashPig\PaymentProviders\Adyen\Maintenance;

require __DIR__ . '/../../../Maintenance/MaintenanceBase.php';

use SmashPig\Core\Context;
use SmashPig\Maintenance\MaintenanceBase;

class TestAdyenConnectivity extends MaintenanceBase {

	public function __construct() {
		parent::__construct();
		$this->desiredOptions['config-node']['default'] = 'adyen';
		$this->addOption( 'country', '2 letter ISO country code', 'US' );
		$this->addOption( 'currency', '3 letter ISO currency code', 'USD' );
		$this->addOption( 'amount', 'Amount in major currency units', '1.23' );
		$this->addOption( 'language', 'Locale to send', 'en' );
	}

	public function execute() {
		$providerConfiguration = Context::get()->getProviderConfiguration();
		$api = $providerConfiguration->object( 'api' );
		$result = $api->getPaymentMethods( [
			'country' => $this->getOption( 'country' ),
			'currency' => $this->getOption( 'currency' ),
			'amount' => $this->getOption( 'amount' ),
			'language' => $this->getOption( 'language' )
		] );
		print_r( $result );
	}
}

$maintClass = TestAdyenConnectivity::class;

require RUN_MAINTENANCE_IF_MAIN;
