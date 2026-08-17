<?php

namespace SmashPig\PaymentProviders\Gravy\Maintenance;

require __DIR__ . '/../../../Maintenance/MaintenanceBase.php';

use SmashPig\Core\Logging\Logger;
use SmashPig\Maintenance\MaintenanceBase;
use SmashPig\PaymentProviders\Gravy\Errors\ErrorHelper;

/**
 * Test ErrorHelper::sendFraudTransactionsEmail functionality to verify fraud alert emails are sent
 */
class TestFraudTransactionEmail extends MaintenanceBase {

	public function __construct() {
		parent::__construct();
		$this->desiredOptions['config-node']['default'] = 'gravy';
	}

	/**
	 * Do the actual work of the script.
	 */
	public function execute(): void {
		$sampleFraudTransactionIds = $this->generateSampleTransactionIds();
		Logger::info( "Calling ErrorHelper::sendFraudTransactionsEmail..." );

		try {
			$result = ErrorHelper::sendFraudTransactionsEmail( $sampleFraudTransactionIds );

			if ( $result ) {
				Logger::info( "Fraud transaction email sent successfully!" );
			} else {
				Logger::error( "Failed to send fraud transaction email." );
			}
		} catch ( \Exception $e ) {
			Logger::error( "Exception occurred while sending fraud email: " . $e->getMessage() );
		}
	}

	/**
	 * Generate sample transaction IDs for testing purposes
	 */
	private function generateSampleTransactionIds(): array {
		return [
			[
				'id' => '12345678-1234-5678-9abc-def012345678',
				'summary' => [
					'backend_processor' => 'Adyen',
					'external_identifier' => '166.3',
					'amount' => 'USD 12.99',
					'method' => 'card',
					'bin' => '41111',
					'country' => 'US'
				],
			],
			[
				'id' => 'abcdef12-3456-7890-1234-567890abcdef',
				'summary' => [
					'backend_processor' => 'PayPal',
					'external_identifier' => '166.3',
					'amount' => 'USD 12.99',
					'method' => 'paypal',
					'country' => 'US'
				],
			],
			[
				'id' => '98765432-dcba-4321-8765-432109876543',
				'summary' => [
					'backend_processor' => 'Trustly',
					'external_identifier' => '168.1',
					'amount' => 'USD 12.99',
					'method' => 'rtbt',
					'country' => 'US'
				],
			],
			[
				'id' => 'fedcba98-7654-3210-9876-543210fedcba',
				'summary' => [
					'backend_processor' => 'Braintree',
					'external_identifier' => '169.1',
					'amount' => 'USD 12.99',
					'method' => 'venmo',
					'country' => 'US'
				],
			],
			[
				'id' => 'a1b2c3d4-e5f6-7890-1234-567890123456',
				'summary' => [
					'backend_processor' => 'dLocal',
					'external_identifier' => '170.1',
					'amount' => 'USD 12.99',
					'method' => 'card',
					'bin' => '41111',
					'country' => 'US'
				],
			]
		];
	}
}

$maintClass = TestFraudTransactionEmail::class;

require RUN_MAINTENANCE_IF_MAIN;
