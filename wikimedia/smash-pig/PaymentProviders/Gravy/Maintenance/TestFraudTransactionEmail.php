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
				'info' => ' - 1299 USD, via Adyen'
			],
			[
				'id' => 'abcdef12-3456-7890-1234-567890abcdef',
				'info' => ' - 1299 USD, via Adyen'
			],
			[
				'id' => '98765432-dcba-4321-8765-432109876543',
				'info' => ' - 1299 USD, via Adyen'
			],
			[
				'id' => 'fedcba98-7654-3210-9876-543210fedcba',
				'info' => ' - 1299 USD, via Adyen'
			],
			[
				'id' => 'a1b2c3d4-e5f6-7890-1234-567890123456',
				'info' => ' - 1299 USD, via Adyen'
			],
			[
				'id' => '11111111-2222-3333-4444-555555555555',
				'info' => ' - 1299 USD, via Adyen'
			],
			[
				'id' => '22222222-3333-4444-5555-666666666666',
				'info' => ' - 1299 USD, via Adyen'
			],
			[
				'id' => '33333333-4444-5555-6666-777777777777',
				'info' => ' - 1299 USD, via Adyen'
			],
			[
				'id' => '44444444-5555-6666-7777-888888888888',
				'info' => ' - 1299 USD, via Adyen'
			],
			[
				'id' => '55555555-6666-7777-8888-999999999999',
				'info' => ' - 1299 USD, via Adyen'
			],
			[
				'id' => '66666666-7777-8888-9999-aaaaaaaaaaaa',
				'info' => ' - 1299 USD, via Adyen'
			],
			[
				'id' => '77777777-8888-9999-aaaa-bbbbbbbbbbbb',
				'info' => ' - 1299 USD, via Adyen'
			],
			[
				'id' => '88888888-9999-aaaa-bbbb-cccccccccccc',
				'info' => ' - 1299 USD, via Adyen'
			],
			[
				'id' => '99999999-aaaa-bbbb-cccc-dddddddddddd',
				'info' => ' - 1299 USD, via Adyen'
			],
			[
				'id' => 'aaaaaaaa-bbbb-cccc-dddd-eeeeeeeeeeee',
				'info' => ' - 1299 USD, via Adyen'
			],
			[
				'id' => 'bbbbbbbb-cccc-dddd-eeee-ffffffffffff',
				'info' => ' - 1299 USD, via Adyen'
			],
			[
				'id' => 'cccccccc-dddd-eeee-ffff-000000000000',
				'info' => ' - 1299 USD, via Adyen'
			],
			[
				'id' => 'dddddddd-eeee-ffff-0000-111111111111',
				'info' => ' - 1299 USD, via Adyen'
			],
			[
				'id' => 'eeeeeeee-ffff-0000-1111-222222222222',
				'info' => ' - 1299 USD, via Adyen'
			],
			[
				'id' => 'ffffffff-0000-1111-2222-333333333333',
				'info' => ' - 1299 USD, via Adyen'
			]
		];
	}
}

$maintClass = TestFraudTransactionEmail::class;

require RUN_MAINTENANCE_IF_MAIN;
