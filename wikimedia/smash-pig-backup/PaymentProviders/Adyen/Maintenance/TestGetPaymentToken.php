<?php

namespace SmashPig\PaymentProviders\Adyen\Maintenance;

require __DIR__ . '/../../../Maintenance/MaintenanceBase.php';

use SmashPig\Core\Logging\Logger;
use SmashPig\Maintenance\MaintenanceBase;
use SmashPig\PaymentProviders\Adyen\PaymentProvider;
use SmashPig\PaymentProviders\PaymentProviderFactory;

/**
 * Test out a recurring Adyen transaction
 */
class TestGetPaymentToken extends MaintenanceBase {

	public function __construct() {
		parent::__construct();
		$this->addOption( 'pcid', 'Processor contact ID', false );
		$this->addOption( 'method', 'payment method', 'cc' );

		$this->desiredOptions['config-node']['default'] = 'adyen';
	}

	/**
	 * Do the actual work of the script.
	 */
	public function execute(): void {
		/**
		 * @var PaymentProvider
		 */
		$adyen = PaymentProviderFactory::getProviderForMethod( $this->getOption( 'method' ) );

		$savedDetailsResponse = $adyen->getSavedPaymentDetails( $this->getOption( 'pcid' ) );
		Logger::info( "Tokenize result: " . print_r( $savedDetailsResponse, true ) );
	}
}

$maintClass = TestGetPaymentToken::class;

require RUN_MAINTENANCE_IF_MAIN;
