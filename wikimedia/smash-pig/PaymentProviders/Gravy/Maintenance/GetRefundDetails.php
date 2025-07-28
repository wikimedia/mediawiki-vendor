<?php

namespace SmashPig\PaymentProviders\Gravy\Maintenance;

require __DIR__ . '/../../../Maintenance/MaintenanceBase.php';

use SmashPig\Core\Logging\Logger;
use SmashPig\Maintenance\MaintenanceBase;
use SmashPig\PaymentProviders\PaymentProviderFactory;

/**
 * Get latest transaction status
 */
class GetRefundDetails extends MaintenanceBase {

	public function __construct() {
		parent::__construct();
		$this->desiredOptions['config-node']['default'] = 'gravy';
		$this->addArgument( 'refund_id', 'ID of required transaction', true );
	}

	public function execute(): void {
		$refundID = $this->getArgument( 'refund_id' );

		$provider = PaymentProviderFactory::getDefaultProvider();
		try {
			print_r( $provider->getRefundDetails( [
				'gateway_refund_id' => $refundID
			] ) );
		} catch ( \Exception $ex ) {
			Logger::info( "Could not find refund with transaction id $refundID", null, $ex );
		}
	}
}

$maintClass = GetRefundDetails::class;

require RUN_MAINTENANCE_IF_MAIN;
