<?php namespace SmashPig\PaymentProviders\Adyen\Jobs;

use SmashPig\Core\Context;
use SmashPig\Core\Logging\TaggedLogger;
use SmashPig\Core\Runnable;
use SmashPig\Core\SmashPigException;

/**
 * Download Adyen settlement detail reports. These reports are named
 * settlement_detail_report_batch_[n].csv
 *
 * @package SmashPig\PaymentProviders\Adyen\Jobs
 */
class DownloadReportJob implements Runnable {
	public array $payload;

	protected TaggedLogger $logger;

	protected string $downloadLoc;

	public static function factory( $account, $url ): array {
		return [
			'class' => 'SmashPig\PaymentProviders\Adyen\Jobs\DownloadReportJob',
			'payload' => [
				'account' => $account,
				'reportUrl' => $url,
			]
		];
	}

	public function execute() {
		$this->logger = new TaggedLogger( __CLASS__ );
		$c = Context::get()->getProviderConfiguration();

		// Construct the temporary file path
		$fileName = basename( $this->payload['reportUrl'] );
		$this->downloadLoc =
			$c->val( "accounts/{$this->payload['account']}/report-location" ) . '/' .
			$fileName;

		$user = $c->val( "accounts/{$this->payload['account']}/report-username" );
		$pass = $c->val( "accounts/{$this->payload['account']}/report-password" );

		$this->logger->info(
			"Beginning report download from {$this->payload['reportUrl']} using username {$user} into {$this->downloadLoc}"
		);

		$fp = fopen( $this->downloadLoc, 'w' );
		if ( !$fp ) {
			$str = "Could not open {$this->downloadLoc} for writing! Will not download report.";
			$this->logger->error( $str );
			throw new SmashPigException( $str );
		}

		$ch = curl_init();
		curl_setopt( $ch, CURLOPT_URL, $this->payload['reportUrl'] );

		curl_setopt( $ch, CURLOPT_RETURNTRANSFER, false );
		curl_setopt( $ch, CURLOPT_FILE, $fp );

		curl_setopt( $ch, CURLOPT_FOLLOWLOCATION, 1 );

		curl_setopt( $ch, CURLOPT_SSL_VERIFYPEER, true );
		curl_setopt( $ch, CURLOPT_SSL_VERIFYHOST, 2 );
		curl_setopt( $ch, CURLOPT_HTTPAUTH, CURLAUTH_ANY );
		curl_setopt( $ch, CURLOPT_USERPWD, "{$user}:{$pass}" );

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
