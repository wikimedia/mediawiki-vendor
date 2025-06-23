<?php

namespace SmashPig\PaymentProviders\Gravy\Maintenance;

require __DIR__ . '/../../../Maintenance/MaintenanceBase.php';

use SmashPig\Core\Logging\Logger;
use SmashPig\Maintenance\MaintenanceBase;
use SmashPig\PaymentProviders\PaymentProviderFactory;

/**
 * Get latest transaction status
 */
class GetTransactionDetails extends MaintenanceBase {

	public function __construct() {
		parent::__construct();
		$this->desiredOptions['config-node']['default'] = 'gravy';
		$this->addArgument( 'transaction_id', 'ID of required transaction', true );
	}

	public function execute(): void {
		$transactionID = $this->getArgument( 'transaction_id' );

		$provider = PaymentProviderFactory::getDefaultProvider();
		try {
			$provider->getLatestPaymentStatus( [
				'gateway_txn_id' => $transactionID
			] );
		} catch ( \Exception $ex ) {
			Logger::error( "Could not find payment with transaction id $transactionID", null, $ex );
		}
	}
}

$maintClass = GetTransactionDetails::class;

require RUN_MAINTENANCE_IF_MAIN;
