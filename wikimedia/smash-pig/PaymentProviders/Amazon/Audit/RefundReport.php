<?php namespace SmashPig\PaymentProviders\Amazon\Audit;

use SmashPig\Core\DataFiles\DataFileException;
use SmashPig\Core\DataFiles\HeadedCsvReader;
use SmashPig\Core\Logging\Logger;
use SmashPig\Core\UtcDate;

/**
 * Parses off-Amazon payments refund reports retrieved from Amazon's MWS
 * http://amazonpayments.s3.amazonaws.com/documents/Sample%20Settlement%20Report.pdf#page=25
 */
class RefundReport {
	protected $fileData;

	public static function isMine( $filename ) {
		return preg_match( '/.*REFUND_DATA.*csv/', $filename );
	}

	public function parse( $path ) {
		$this->fileData = [];
		$csv = new HeadedCsvReader( $path, ',', 4096, 0 );

		while ( $csv->valid() ) {
			try {
				$this->parseLine( $csv );
				$csv->next();
			} catch ( DataFileException $ex ) {
				Logger::error( $ex->getMessage() );
			}
		}

		return $this->fileData;
	}

	/**
	 * @param HeadedCsvReader $csv
	 */
	protected function parseLine( HeadedCsvReader $csv ) {
		$status = $csv->currentCol( 'RefundStatus' );

		// Only process completed
		if ( $status !== 'Completed' ) {
			return;
		}

		$msg = [];
		$msg['date'] = UtcDate::getUtcTimestamp(
			$csv->currentCol( 'LastUpdateTimestamp' )
		);
		$msg['gateway'] = 'amazon';
		$msg['gateway_parent_id'] = $csv->currentCol( 'AmazonCaptureId' );
		$msg['gateway_refund_id'] = $csv->currentCol( 'AmazonRefundId' );
		$msg['gross'] = $csv->currentCol( 'RefundAmount' );
		$msg['gross_currency'] = $csv->currentCol( 'CurrencyCode' );
		if ( $csv->currentCol( 'RefundType' ) === 'SellerInitiated' ) {
			$msg['type'] = 'refund';
		} else {
			$msg['type'] = 'chargeback';
		}

		$this->fileData[] = $msg;
	}
}
