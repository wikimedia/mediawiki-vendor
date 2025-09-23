<?php

namespace SmashPig\PaymentProviders\Gravy\Maintenance;

require __DIR__ . '/../../../Maintenance/MaintenanceBase.php';

use SmashPig\Core\Helpers\CurrencyRoundingHelper;
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
				'summary' => ' - Adyen, 166.3, USD ' . CurrencyRoundingHelper::getAmountInMajorUnits( 1299, 'USD' ) . ', via card, from US'
			],
			[
				'id' => 'abcdef12-3456-7890-1234-567890abcdef',
				'summary' => ' - PayPal, 166.3, USD ' . CurrencyRoundingHelper::getAmountInMajorUnits( 1299, 'USD' ) . ', via paypal, from US'
			],
			[
				'id' => '98765432-dcba-4321-8765-432109876543',
				'summary' => ' - Trustly, 166.3, USD ' . CurrencyRoundingHelper::getAmountInMajorUnits( 1299, 'USD' ) . ', via rtbt, from US'
			],
			[
				'id' => 'fedcba98-7654-3210-9876-543210fedcba',
				'summary' => ' - Braintree, 166.3, USD ' . CurrencyRoundingHelper::getAmountInMajorUnits( 1299, 'USD' ) . ', via venmo, from US'
			],
			[
				'id' => 'a1b2c3d4-e5f6-7890-1234-567890123456',
				'summary' => ' - dLocal, 166.3, USD ' . CurrencyRoundingHelper::getAmountInMajorUnits( 1299, 'USD' ) . ', via card, from US'
			]
		];
	}
}

$maintClass = TestFraudTransactionEmail::class;

require RUN_MAINTENANCE_IF_MAIN;
