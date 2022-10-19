<?php

namespace SmashPig\PaymentProviders\Amazon\Maintenance;

require __DIR__ . '/../../../Maintenance/MaintenanceBase.php';
use SmashPig\Maintenance\MaintenanceBase;
use SmashPig\PaymentProviders\Amazon\AmazonApi;

class AuthorizeAndCapture extends MaintenanceBase {

	public function __construct() {
		parent::__construct();
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
		$f = fopen( $filename, 'r' );
		if ( !$f ) {
			$this->error( "Could not open $filename for read", true );
		}

		// Do the loop!
		while ( ( $line = fgets( $f ) ) !== false ) {
			$orderRef = trim( $line );
			try {
				$result = $api->authorizeAndCapture( $orderRef );
				if ( $result['AuthorizationStatus']['State'] === 'Declined' ) {
					$reason = $result['AuthorizationStatus']['ReasonCode'];
					print( "Order $orderRef authorization was declined: $reason\n" );
				} else {
					$captureId = $result['IdList']['member'];
					print( "Order $orderRef authorized. Capture ID is $captureId\n" );
				}
			} catch ( \Exception $ex ) {
				print( "Order $orderRef threw an exception: " . $ex->getMessage() . "\n" );
			}
		}
	}
}

$maintClass = AuthorizeAndCapture::class;

require RUN_MAINTENANCE_IF_MAIN;
