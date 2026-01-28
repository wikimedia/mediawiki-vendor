<?php

namespace SmashPig\PaymentProviders\PayPal\Maintenance;

require __DIR__ . '/../../../Maintenance/MaintenanceBase.php';

use SmashPig\Core\Logging\Logger;
use SmashPig\Maintenance\MaintenanceBase;
use SmashPig\PaymentProviders\PaymentProviderFactory;

class GetTransactionDetails extends MaintenanceBase {

	public function __construct() {
		parent::__construct();
		$this->desiredOptions['config-node']['default'] = 'paypal';
		$this->addOption(
			'transaction-id',
			'PayPal transaction ID to look up',
			true,
			true
		);
	}

	public function execute() {
		$transactionId = $this->getOption( 'transaction-id' );

		if ( !$transactionId ) {
			$this->error( 'You must supply --transaction-id', true );
		}

		Logger::info( "Looking up PayPal transaction: {$transactionId}" );
		/** @var \SmashPig\PaymentProviders\PayPal\PaymentProvider $provider */
		$provider = PaymentProviderFactory::getProviderForMethod( 'paypal' );

		$api = $provider->getApi();

		try {
			$result = $api->getTransactionDetails( $transactionId );

			echo "=== Transaction Search Result ===\n";
			echo json_encode( $result, JSON_PRETTY_PRINT ) . "\n";

		} catch ( \Exception $e ) {
			$this->error(
				"Error fetching transaction {$transactionId}: " . $e->getMessage(),
				true
			);
		}
	}
}

$maintClass = GetTransactionDetails::class;
require_once RUN_MAINTENANCE_IF_MAIN;
