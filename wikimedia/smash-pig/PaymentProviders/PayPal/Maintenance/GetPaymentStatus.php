<?php

namespace SmashPig\PaymentProviders\PayPal\Maintenance;

require __DIR__ . '/../../../Maintenance/MaintenanceBase.php';

use SmashPig\Maintenance\MaintenanceBase;
use SmashPig\PaymentProviders\PaymentProviderFactory;

$maintClass = 'SmashPig\PaymentProviders\PayPal\Maintenance\GetPaymentStatus';

/**
 * Get status of a payment session
 */
class GetPaymentStatus extends MaintenanceBase {

	public function __construct() {
		parent::__construct();
		$this->desiredOptions['config-node']['default'] = 'paypal';
		$this->addArgument( 'gateway_session_id', 'Gateway session ID / PayPal EC Token' );
	}

	/**
	 * Do the actual work of the script.
	 */
	public function execute() {
		$provider = PaymentProviderFactory::getProviderForMethod( 'paypal' );
		$result = $provider->getLatestPaymentStatus(
			[ 'gateway_session_id' => $this->getArgument( 'gateway_session_id' ) ]
		);
		print_r( $result );
	}
}

require RUN_MAINTENANCE_IF_MAIN;
