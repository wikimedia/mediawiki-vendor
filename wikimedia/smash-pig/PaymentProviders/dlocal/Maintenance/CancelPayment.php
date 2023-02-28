<?php

namespace SmashPig\PaymentProviders\dlocal\Maintenance;

require __DIR__ . '/../../../Maintenance/MaintenanceBase.php';

use SmashPig\Core\Logging\Logger;
use SmashPig\Maintenance\MaintenanceBase;
use SmashPig\PaymentProviders\dlocal\PaymentProvider;
use SmashPig\PaymentProviders\PaymentProviderFactory;

/**
 *	A valid response will return the transaction detail with a new status as "status": "CANCELLED",
 */
class CancelPayment extends MaintenanceBase {

	public function __construct() {
		parent::__construct();
		$this->desiredOptions['config-node']['default'] = 'dlocal';
		$this->addArgument( 'id', 'Gateway transaction ID (gateway_txn_id)', true );
	}

	public function execute(): void {
		/** @var PaymentProvider $paymentProvider */
		$paymentProvider = PaymentProviderFactory::getProviderForMethod( 'cc' );
		$gatewayId = $this->getArgument( 'id' );
		$cancelPaymentResponse = $paymentProvider->cancelPayment( $gatewayId );
		Logger::info( "Cancel payment result: " . print_r( $cancelPaymentResponse, true ) );
	}
}

$maintClass = CancelPayment::class;

require RUN_MAINTENANCE_IF_MAIN;
