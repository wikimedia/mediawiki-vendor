<?php

namespace SmashPig\PaymentProviders\Gravy\Maintenance;

require __DIR__ . '/../../../Maintenance/MaintenanceBase.php';

use SmashPig\Core\Logging\Logger;
use SmashPig\Maintenance\MaintenanceBase;
use SmashPig\PaymentProviders\Gravy\Errors\ErrorTracker;

/**
 * Test ErrorTracker Redis connectivity by creating an instance and tracking a test error
 */
class TestErrorTrackerConnectivity extends MaintenanceBase {

	public function __construct() {
		parent::__construct();
		$this->desiredOptions['config-node']['default'] = 'gravy';
	}

	/**
	 * Do the actual work of the script.
	 */
	public function execute(): void {
		try {
			Logger::info( "Testing ErrorTracker Redis connectivity..." );

			$errorTrackerConfig = [
				'enabled' => true,
				'threshold' => 10,
				'time_window' => 300, // 5 minutes
				'key_prefix' => 'smashpig_gravy_error_test_',
				'key_expiry_period' => 600, // 10 minutes
				'alert_suppression_period' => 600 // 10 minutes
			];

			$errorTracker = new ErrorTracker( $errorTrackerConfig );

			$testError = [
				'error_code' => 'connection_test',
				'error_type' => 'test_error',
				'sample_transaction_id' => 'test_' . time(),
				'sample_transaction_summary' => ' - Redis connectivity test at ' . date( 'Y-m-d H:i:s' ) . ' for testing purposes'
			];

			Logger::info( "Attempting to track test error to confirm Redis connectivity:", $testError );

			// this one should get recorded
			$alertTriggered = $errorTracker->trackErrorAndCheckThreshold( $testError );
			// this one should be ignored as it's a duplicate of the first one
			$secondAttempt = $errorTracker->trackErrorAndCheckThreshold( $testError );

			if ( !$alertTriggered || !$secondAttempt ) {
				throw new \RuntimeException(
					"Redis connectivity test failed: Error tracking operations did not succeed. " .
					"Check Redis connection and configuration."
				);
			}

			Logger::info( "Redis connection established and operations completed successfully!" );

		} catch ( \Exception $e ) {
			Logger::error( "FAILED: ErrorTracker Redis connectivity test failed with exception:", [
				'message' => $e->getMessage(),
				'file' => $e->getFile(),
				'line' => $e->getLine()
			] );
			throw $e;
		}
	}
}

$maintClass = TestErrorTrackerConnectivity::class;

require RUN_MAINTENANCE_IF_MAIN;
