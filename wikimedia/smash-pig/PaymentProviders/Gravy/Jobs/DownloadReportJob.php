<?php namespace SmashPig\PaymentProviders\Gravy\Jobs;

use SmashPig\Core\Context;
use SmashPig\Core\Logging\TaggedLogger;
use SmashPig\Core\Runnable;
use SmashPig\Core\SmashPigException;
use SmashPig\PaymentProviders\Gravy\Factories\GravyReportResponseFactory;

/**
 * Download Gravy settlement detail reports. These reports are named
 * gravy_settlement_report_Y_m_d.csv
 *
 * @package SmashPig\PaymentProviders\Gravy\Jobs
 */
class DownloadReportJob implements Runnable {
	public array $payload;

	protected TaggedLogger $logger;

	protected string $downloadLoc;

	public static function factory( $message ): array {
		return [
			'class' => 'SmashPig\PaymentProviders\Gravy\Jobs\DownloadReportJob',
			'payload' => $message
		];
	}

	public function execute() {
		$this->logger = new TaggedLogger( __CLASS__ );
		$c = Context::get()->getProviderConfiguration();
		$reportResponse = GravyReportResponseFactory::fromNormalizedResponse( $this->payload );

		// generate the filename using the current time and data
		$date = date( 'Y_m_d' );
		$fileName = "gravy_settlement_report_{$date}.csv";

		$this->downloadLoc =
			$c->val( "report-location" ) . '/' .
			$fileName;

		$this->logger->info(
			"Beginning report download from {$reportResponse->getReportUrl()}"
		);

		$fp = fopen( $this->downloadLoc, 'w' );
		if ( !$fp ) {
			$str = "Could not open {$this->downloadLoc} for writing! Will not download report.";
			$this->logger->error( $str );
			throw new SmashPigException( $str );
		}

		$ch = curl_init();
		curl_setopt( $ch, CURLOPT_URL, $reportResponse->getReportUrl() );

		// Set up the proxy for google storage T375492
		curl_setopt( $ch, CURLOPT_PROXY, 'frpm1002.frack.eqiad.wmnet' );
		curl_setopt( $ch, CURLOPT_PROXYPORT, 3128 );

		curl_setopt( $ch, CURLOPT_RETURNTRANSFER, false );
		curl_setopt( $ch, CURLOPT_FILE, $fp );

		curl_setopt( $ch, CURLOPT_FOLLOWLOCATION, 1 );

		curl_setopt( $ch, CURLOPT_SSL_VERIFYPEER, true );
		curl_setopt( $ch, CURLOPT_SSL_VERIFYHOST, 2 );
		curl_setopt( $ch, CURLOPT_HTTPAUTH, CURLAUTH_ANY );

		$result = curl_exec( $ch );
		$httpCode = curl_getinfo( $ch, CURLINFO_HTTP_CODE );
		$error = curl_error( $ch );
		curl_close( $ch );

		if ( $result === false ) {
			$this->logger->error( "Could not download report due to cURL error {$error}" );
			throw new SmashPigException( "Could not download report." );
		} elseif ( $httpCode !== 200 ) {
			$this->logger->error( "Report downloaded(?), but with incorrect HTTP code: {$httpCode}" );
			throw new SmashPigException( "Could not download report." );
		}
		return true;
	}
}
