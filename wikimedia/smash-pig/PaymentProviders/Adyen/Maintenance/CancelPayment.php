<?php

namespace SmashPig\PaymentProviders\Adyen\Maintenance;

require __DIR__ . '/../../../Maintenance/MaintenanceBase.php';

use SmashPig\Core\Logging\Logger;
use SmashPig\Maintenance\MaintenanceBase;
use SmashPig\PaymentProviders\Adyen\PaymentProvider;
use SmashPig\PaymentProviders\PaymentProviderFactory;

/**
 * Test out the Adyen cancel rest endpoint
 */
class CancelPayment extends MaintenanceBase {

	public function __construct() {
		parent::__construct();
		$this->addOption( 'id', 'Gateway transaction ID (PSP Reference)', false );
		$this->addOption( 'method', 'payment method', 'cc' );

		$this->desiredOptions['config-node']['default'] = 'adyen';
	}

	/**
	 * Do the actual work of the script.
	 */
	public function execute() {
		/**
		 * @var PaymentProvider
		 */
		$adyen = PaymentProviderFactory::getProviderForMethod( $this->getOption( 'method' ) );

		$cancelPaymentResponse = $adyen->cancelPayment( $this->getOption( 'id' ) );
		Logger::info( "Cancel payment result: " . print_r( $cancelPaymentResponse, true ) );
	}
}

$maintClass = CancelPayment::class;

require RUN_MAINTENANCE_IF_MAIN;
