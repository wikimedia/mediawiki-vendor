<?php

namespace SmashPig\PaymentProviders\Amazon\Maintenance;

require __DIR__ . '/../../../Maintenance/MaintenanceBase.php';
use SmashPig\Maintenance\MaintenanceBase;
use SmashPig\PaymentProviders\Amazon\AmazonApi;

class Cancel extends MaintenanceBase {

	public function __construct() {
		parent::__construct();
		$this->addOption( 'reason', 'Reason for cancellation', '' );
		$this->addArgument(
			'file',
			'File with one order reference ID per line',
			true
		);
		$this->desiredOptions['config-node']['default'] = 'amazon';
	}

	public function execute() {
		$api = AmazonApi::get();

		$filename = $this->getArgument( 'file' );
		$reason = $this->getOption( 'reason' );

		$f = fopen( $filename, 'r' );
		if ( !$f ) {
			$this->error( "Could not open $filename for read", true );
		}

		// Do the loop!
		while ( ( $line = fgets( $f ) ) !== false ) {
			$orderRef = trim( $line );
			try {
				$api->cancelOrderReference( $orderRef, $reason );
				print "Canceled $orderRef\n";
			} catch ( \Exception $ex ) {
				print(
					"Canceling $orderRef had an error: " . $ex->getMessage() . "\n"
				);
			}
		}
	}
}

$maintClass = Cancel::class;

require RUN_MAINTENANCE_IF_MAIN;
