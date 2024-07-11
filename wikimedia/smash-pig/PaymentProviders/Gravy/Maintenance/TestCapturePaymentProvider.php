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
class TestCapturePaymentProvider extends MaintenanceBase {

	public function __construct() {
		parent::__construct();

		$this->desiredOptions['config-node']['default'] = 'gravy';
		$this->addOption( 'currency', 'Currency' );
		$this->addOption( 'amount', 'Amount' );
		$this->addOption( 'sessionId', 'Session ID' );
	}

	/**
	 * Do the actual work of the script.
	 */
	public function execute() {
		/**
		 * @var PaymentProvider
		 */
		$gravy = PaymentProviderFactory::getDefaultProvider();

		$request = [
			'amount' => $this->getOption( 'amount' ),
			'currency' => $this->getOption( 'currency' ),
			'gateway_session_id' => $this->getOption( 'sessionId' ),
		];

		$gravy->createPayment( $request );

		Logger::info( "Result: " . print_r( $request, true ) );
	}
}

$maintClass = TestCapturePaymentProvider::class;

require RUN_MAINTENANCE_IF_MAIN;
