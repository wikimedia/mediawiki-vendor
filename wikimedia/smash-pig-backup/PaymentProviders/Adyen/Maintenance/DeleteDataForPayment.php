<?php

namespace SmashPig\PaymentProviders\Adyen\Maintenance;

require __DIR__ . '/../../../Maintenance/MaintenanceBase.php';

use SmashPig\Core\Logging\Logger;
use SmashPig\Maintenance\MaintenanceBase;
use SmashPig\PaymentProviders\Adyen\PaymentProvider;
use SmashPig\PaymentProviders\PaymentProviderFactory;

class DeleteDataForPayment extends MaintenanceBase {

	public function __construct() {
		parent::__construct();
		$this->desiredOptions['config-node']['default'] = 'adyen';
		$this->addArgument( 'gatewayTxnId', 'Gateway transaction ID (PSP Reference)', true );
	}

	public function execute(): void {
		/** @var PaymentProvider $provider */
		$provider = PaymentProviderFactory::getProviderForMethod( 'cc' );
		$gatewayTxnId = $this->getArgument( 'gatewayTxnId' );
		$response = $provider->deleteDataForPayment( $gatewayTxnId );

		if ( $response->isSuccessful() ) {
			Logger::info( "Requested data deleteion for $gatewayTxnId" );
		} else {
			Logger::warning( "Problem requesting data deleteion for $gatewayTxnId" );
			Logger::info( $response->getRawResponse() );
		}
	}
}

$maintClass = DeleteDataForPayment::class;

require RUN_MAINTENANCE_IF_MAIN;
