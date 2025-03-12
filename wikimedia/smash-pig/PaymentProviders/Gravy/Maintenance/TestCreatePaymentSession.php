<?php

namespace SmashPig\PaymentProviders\Gravy\Maintenance;

require __DIR__ . '/../../../Maintenance/MaintenanceBase.php';

use SmashPig\Core\Logging\Logger;
use SmashPig\Maintenance\MaintenanceBase;
use SmashPig\PaymentProviders\Gravy\PaymentProvider;
use SmashPig\PaymentProviders\PaymentProviderFactory;

/**
 * Test out a recurring Adyen transaction
 */
class TestCreatePaymentSession extends MaintenanceBase {

	public function __construct() {
		parent::__construct();

		$this->desiredOptions['config-node']['default'] = 'gravy';
	}

	/**
	 * Do the actual work of the script.
	 */
	public function execute(): void {
		/**
		 * @var PaymentProvider
		 */
		$gravy = PaymentProviderFactory::getDefaultProvider();

		$paymentSession = $gravy->createPaymentSession();
		Logger::info( 'Result: ' . print_r( $paymentSession, true ) );
	}
}

$maintClass = TestCreatePaymentSession::class;

require RUN_MAINTENANCE_IF_MAIN;
