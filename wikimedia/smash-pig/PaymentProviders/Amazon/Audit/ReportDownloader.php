<?php namespace SmashPig\PaymentProviders\Amazon\Audit;

use DateTime;
use DateTimeZone;
use PayWithAmazon\ReportsClient;
use PayWithAmazon\ReportsClientInterface;
use SmashPig\Core\Context;
use SmashPig\Core\Logging\Logger;

/**
 * Downloads transaction reports via MWS
 * http://amazonpayments.s3.amazonaws.com/documents/Programmatic%20Access%20to%20Amazon%20Payments%20Reports.pdf
 */
class ReportDownloader {

	protected $archivePath;
	protected $downloadPath;
	protected $days;
	protected $downloadedIds = [];

	const FILE_REGEX = '/\d{4}-\d{2}-\d{2}-[_A-Z0-9]+_(?P<id>\d+).csv/';

	/**
	 * @var ReportsClientInterface
	 */
	protected $reportsClient;

	public function __construct( $overrides ) {
		$config = Context::get()->getProviderConfiguration();
		$this->archivePath =
			empty( $overrides['archive-path'] )
			? $config->val( 'audit/archive-path' )
			: $overrides['archive-path'];
		$this->downloadPath =
			empty( $overrides['download-path'] )
			? $config->val( 'audit/download-path' )
			: $overrides['download-path'];
		$this->days = $overrides['days'];
	}

	protected function ensureAndScanFolder( $path ) {
		if ( !is_dir( $path ) ) {
			if ( file_exists( $path ) ) {
				throw new \RuntimeException( "$path exists and is not a directory!" );
			}
			Logger::info( "Creating missing directory $path" );
			if ( !mkdir( $path ) ) {
				throw new \RuntimeException( "Unable to create directory $path!" );
			}
		}
		foreach ( scandir( $path ) as $file ) {
			if ( preg_match( self::FILE_REGEX, $file, $matches ) ) {
				$this->downloadedIds[] = $matches['id'];
			}
		}
	}

	public function download() {
		$this->ensureAndScanFolder( $this->archivePath );
		$this->ensureAndScanFolder( $this->downloadPath );

		$this->reportsClient =
			Context::get()->getProviderConfiguration()->object( 'reports-client', true );

		Logger::info( 'Getting report list' );
		$startDate = new DateTime( "-{$this->days} days", new DateTimeZone( 'UTC' ) );
		$list = $this->reportsClient->getReportList( [
			'available_from_date' => $startDate->format( DateTime::ATOM ),
			'max_count' => 100,
			'report_type_list' => [
				ReportsClient::OFFAMAZONPAYMENTS_SETTLEMENT,
				ReportsClient::OFFAMAZONPAYMENTS_REFUND,
			],
		] )->toArray();
		foreach ( $list['GetReportListResult']['ReportInfo'] as $reportInfo ) {
			// If you're planning to download more than 15 reports at a time, be
			// aware that the client will handle throttling by default, retrying
			// up to four times with successively longer wait times.
			$this->downloadReport( $reportInfo );
		}
	}

	protected function downloadReport( $reportInfo ) {
		$id = $reportInfo['ReportId'];
		// Remove common prefix from report type
		$type = str_replace(
			'_GET_FLAT_FILE_OFFAMAZONPAYMENTS_',
			'',
			$reportInfo['ReportType']
		);
		if ( array_search( $id, $this->downloadedIds ) === false ) {
			Logger::debug( "Downloading report dated {$reportInfo['AvailableDate']} with id: $id" );
			$report = $this->reportsClient->getReport( [
				'report_id' => $id,
			] );
			$date = substr( $reportInfo['AvailableDate'], 0, 10 );
			$path = "{$this->downloadPath}/{$date}-{$type}{$id}.csv";
			Logger::info( "Saving report to $path" );
			file_put_contents( $path, $report['ResponseBody'] );
		} else {
			Logger::debug( "Skipping downloaded report with id: $id" );
		}
	}
}
