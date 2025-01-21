<?php

namespace SmashPig\PaymentProviders\dlocal\Maintenance;

require __DIR__ . '/../../../Maintenance/MaintenanceBase.php';
use SmashPig\Maintenance\MaintenanceBase;
use SmashPig\PaymentProviders\dlocal\PaymentProvider;

/**
 *	A valid response from this authorize will return a rediect_url that you can paste into the browser to
 * complete the payment
 *
 */
class TestGetPaymentStatus extends MaintenanceBase {

	public function __construct() {
		parent::__construct();
		$this->desiredOptions['config-node']['default'] = 'dlocal';
		$this->addArgument( 'id', 'Gateway transaction ID (gateway_txn_id)', true );
	}

	public function execute(): void {
		$paymentProvider = new PaymentProvider();
		$params['gateway_txn_id'] = $this->getArgument( 'id' );
		$paymentDetailResponse = $paymentProvider->getLatestPaymentStatus( $params );
		print_r( $paymentDetailResponse );
	}
}

$maintClass = TestGetPaymentStatus::class;

require RUN_MAINTENANCE_IF_MAIN;
