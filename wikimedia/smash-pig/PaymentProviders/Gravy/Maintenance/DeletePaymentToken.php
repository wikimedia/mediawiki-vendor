<?php

namespace SmashPig\PaymentProviders\Gravy\Maintenance;

require __DIR__ . '/../../../Maintenance/MaintenanceBase.php';

use SmashPig\Core\DataFiles\HeadedCsvReader;
use SmashPig\Core\Logging\Logger;
use SmashPig\Maintenance\MaintenanceBase;
use SmashPig\PaymentProviders\PaymentProviderFactory;

/**
 * Batch delete recurring_payment_token from Gravy. Required argument
 * is the path of a CSV file containing at one column:
 * recurring_payment_token.
 */
class DeletePaymentToken extends MaintenanceBase {

	public function __construct() {
		parent::__construct();
		$this->desiredOptions['config-node']['default'] = 'gravy';
		$this->addArgument( 'file', 'CSV file containing payment parameters', true );
	}

	public function execute(): void {
		$filePath = $this->getArgument( 'file' );
		$reader = new HeadedCsvReader( $filePath );
		$headerList = implode( ',', $reader->headers() );
		Logger::info( "Opened CSV $filePath and found columns $headerList" );

		$required = [ 'recurring_payment_token' ];
		foreach ( $required as $columnName ) {
			if ( array_search( $columnName, $reader->headers() ) === false ) {
				throw new \RuntimeException(
					"CSV file $filePath does not contain a column called '$columnName'"
				);
			}
		}

		$provider = PaymentProviderFactory::getDefaultProvider();

		while ( $reader->valid() ) {
			$params = $reader->currentArray();
			// Our recurring_payment_token corresponds to Gravy's recurring_method_id.
			$paymentToken = $params['recurring_payment_token'];

			try {
				$provider->deleteRecurringPaymentToken( $params );
				Logger::info( "Successfully deleted payment token $paymentToken" );
			} catch ( \Exception $ex ) {
				Logger::error( "Could not delete payment with token id $paymentToken", null, $ex );
			}
			$reader->next();
		}
	}
}

$maintClass = DeletePaymentToken::class;

require RUN_MAINTENANCE_IF_MAIN;
