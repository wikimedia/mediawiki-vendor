<?php
namespace SmashPig\Maintenance;

require 'MaintenanceBase.php';

use SmashPig\Core\Context;
use SmashPig\Core\DataStores\QueueWrapper;
use SmashPig\Core\Logging\Logger;

/**
 * Maintenance script to search logs, extract URL strings,
 * build a contribution tracking queue message, and push it into the queue.
 *
 * Useful for backfilling or correcting missed contribution tracking messages.
 *
 * To test locally:
 *
 * 1. Create a logs directory in docker container, for example:
 *    docker compose exec -u0 smashpig mkdir -p /srv/archive/frlog
 *
 * 2. Update the global config in the main.yaml to update the "logs-archive-directory" to the directory created in step 1, for example:
 * 	  logs-archive-directory: /srv/archive/frlog
 *
 * 2. Copy sample payment logs for a processor to container's temp dir:
 *    docker compose cp /path/to/logs smashpig:/tmp/
 *
 * 3. Symlink logs directory:
 *    docker compose exec -u0 smashpig ln -s /tmp/logs /srv/archive/frlog/logs
 *
 * 4. Run script:
 *    - ./scripts/smashpig.sh
 *    - php Maintenance/PopulateContributionTrackingFieldsFromReferrerURLLogs.php {PROCESSOR_NAME} {DATE_OF_LOGS in YYYYMMDD} <contribution_tracking_id>
 *
 * Example:
 *    php Maintenance/PopulateContributionTrackingFieldsFromReferrerURLLogs.php braintree 20250101 1234567890.1
 */
class PopulateContributionTrackingFieldsFromReferrerURLLogs extends MaintenanceBase {

	public const ZGREP_COMMAND = '/bin/zgrep -H -i %s %s';

	/**
	 * Regex to capture a JSON string with possible nested JSON strings.
	 */
	public const JSON_STRING_REGEX = '/https?:\/\/[^\s]+/';

	public function __construct() {
		parent::__construct();
		$this->addFlag( 'verbose', 'Enable verbose output (show raw matched lines)', 'v' );
		$this->addArgument( 'payment-processor', 'Payment method (e.g gravy)' );
		$this->addArgument( 'date', 'Date of log to find (usually day after date of contribution) (e.g. 20250101)' );
		$this->addArgument( 'id', 'Order ID to find (e.g. 1234567890.1)' );
	}

	/**
	 * Do the actual work of the script.
	 */
	public function execute() {
		$date = $this->getArgument( 'date' );
		$orderId = $this->getArgument( 'id' );
		$ctId = explode( '.', $orderId )[0];
		$paymentProcessor = $this->getArgument( 'payment-processor' );

		if ( empty( $paymentProcessor ) || !is_numeric( $date ) || !is_numeric( $orderId ) ) {
			Logger::info( 'Ensure all parameters are passed in. Both "date" and "id" arguments must be numeric.' );
			Logger::info( 'Example usage: php PopulateContributionTrackingFieldsFromLogs.php gravy 20250101 1234567890.1' );
			return;
		}

		// Search through the logs for matches
		$logSearchMatches = $this->runLogSearch( $paymentProcessor, $orderId, $date );
		if ( empty( $logSearchMatches ) ) {
			Logger::info( "No lines found matching $paymentProcessor transaction with invoice ID '$orderId' for log date '$date'. Exiting." );
			return;
		}

		$referrerURL = [];
		foreach ( $logSearchMatches as $line ) {
			// Split on the first colon only
			$parts = explode( ':', $line, 2 );
			if ( !empty( $parts[1] ) ) {
				$url = $this->findURLFromLogLine( $parts[1] );
				if ( !empty( $url ) ) {
					$referrerURL[] = $url;
				}
			}
		}

		// Populate Contribution Tracking message from URL.
		foreach ( $referrerURL as $line ) {
			$ctMessage = $this->createContributionTrackingMessageFromURL( $line, $ctId );
			if ( !empty( $ctMessage ) ) {
				break;
			}
		}

		Logger::info( "Pushing the following tracking details for transaction with Contribution Tracking ID $ctId to Contribution tracking queue: " . json_encode( $ctMessage ) );
		QueueWrapper::push( 'contribution-tracking', $ctMessage );
	}

	/**
	 * Creates contribution tracking message from the query parameters in the extracted URL
	 *
	 * @param mixed $url
	 * @param mixed $ct_id contribution tracking ID
	 * @return array|array{amount: mixed, country: mixed, currency: mixed, id: mixed, utm_campaign: mixed, utm_key: mixed, utm_medium: mixed, utm_source: mixed}
	 */
	protected function createContributionTrackingMessageFromURL( $url, $ct_id ): array {
		if ( !$url ) {
			return [];
		}
		$url_params = [];
		parse_str( parse_url( $url )['query'], $url_params );

		// Skip urls with empty query parameters
		if ( !isset( $url_params['amount'] ) && !isset( $url_params['wmf_medium'] ) && !isset( $url_params['wmf_campaign'] ) ) {
			return [];
		}

		$contribution_tracking_message = [
			'id' => $ct_id,
			'amount' => $url_params['amount'],
			'currency' => $url_params['currency'],
			'country' => $url_params['country'],
			'utm_source' => $url_params['wmf_source'],
			'utm_medium' => $url_params['wmf_medium'],
			'utm_campaign' => $url_params['wmf_campaign'],
			'utm_key' => $url_params['wmf_key'],
		];

		return $contribution_tracking_message;
	}

	/**
	 * Executes a log search by running a zgrep command for a specific order ID Donor Referrer URL
	 * across log files for a given date.
	 *
	 * @param string $processor Payment Processor used for processing contribution
	 * @param float|int $orderId The ID used to filter log entries.
	 * @param int $date The date in 'YYYYMMDD' format used to locate the relevant log files.
	 *
	 * @return array An array of log lines that match the search criteria.
	 */
	protected function runLogSearch( string $processor, float|int $orderId, int $date ): array {
		$globalConfig = Context::get()->getGlobalConfiguration();
		$log_files_path = $globalConfig->val( 'logs-archive-directory' ) . "/payments-%s-%s.gz";

		$files = glob( sprintf( $log_files_path, $processor, $date ) );
		if ( empty( $files ) ) {
			Logger::info( "No log files matched for date '$date'." );
			return [];
		}

		$combinedOutput = [];
		foreach ( $files as $file ) {
			$command = sprintf( self::ZGREP_COMMAND, "'$orderId Donor Referrer: https://payments.wikimedia.org/index.php'", $file );
			Logger::info( "Scanning logfile: $command" );
			exec( escapeshellcmd( $command ), $output, $exitCode );
			array_push( $combinedOutput, ...$output );
		}

		return $combinedOutput;
	}

	/**
	 * Attempt to find and decode one or more URL strings from a log line.
	 *
	 * @param string $line
	 *
	 * @return string the url in the line
	 */
	protected function findURLFromLogLine( string $line ): string {
		if ( preg_match( self::JSON_STRING_REGEX, $line, $match ) ) {
			if ( !empty( $match ) ) {
				return $match[0];
			}
		}

		return "";
	}
}

$maintClass = PopulateContributionTrackingFieldsFromReferrerURLLogs::class;

require RUN_MAINTENANCE_IF_MAIN;
