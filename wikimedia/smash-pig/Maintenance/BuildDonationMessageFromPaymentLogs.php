<?php

namespace SmashPig\Maintenance;

require 'MaintenanceBase.php';

use SmashPig\Core\DataStores\QueueWrapper;
use SmashPig\Core\Logging\Logger;

/**
 * Maintenance script to search payment logs, extract JSON strings,
 * build a donation queue message, and optionally push it to a queue.
 *
 * Useful for backfilling or correcting missed donation messages.
 * See: T381012
 *
 * Note: To test locally, do the following:
 * 1. Create the expected prod logs directory inside your docker container:
 * - `docker compose exec -u0 smashpig mkdir -p /srv/archive/frlog`
 * 2. Copy some sample payment archive logs into the container’s temporary directory:
 * - `docker compose cp /path/to/logs smashpig:/tmp/`
 * 3. Symlink /tmp/logs to /srv/archive/frlog/logs:
 * - `docker compose exec -u0 smashpig ln -s /tmp/logs /srv/archive/frlog/logs`
 * 4. Run the script:
 * - `./scripts/smashpig.sh`
 * - `php maintenance/BuildDonationMessageFromPaymentLogs.php <YYYYMMDD> <contribution_tracking_id>`
 * - e.g. php Maintenance/BuildDonationMessageFromPaymentLogs.php 20250101 1234567890.1
 */
class BuildDonationMessageFromPaymentLogs extends MaintenanceBase {

	/**
	 * Log location path pattern.
	 *
	 * The '%s' is replaced with the date argument in the path
	 *
	 *
	 */
	public const LOG_FILE_PATH_GREP_PATTERN = '/srv/archive/frlog/logs/*%s.gz';

	/**
	 * zgrep command.
	 *   - First '%s' => contribution ID.
	 *   - Second '%s' => the log file path.
	 */
	public const ZGREP_COMMAND = '/bin/zgrep -H -i %s %s';

	/** Regex to capture a JSON string with possible nested braces. */
	public const JSON_STRING_REGEX = '/\{(?:(?>[^{}]+)|(?R))*}/';

	/** Mapping of log data to donation queue message keys. */
	public const FIELD_MAP = [
		'user_ip' => 'user_ip',
		'gateway_txn_id' => 'gateway_txn_id',
		'gateway' => 'gateway',
		'contribution_tracking_id' => 'contribution_tracking_id',
		'order_id' => 'order_id',
		'currency' => 'currency',
		'country' => 'country',
		'language' => 'language',
		'amount' => 'gross',
		'payment_method' => 'payment_method',
		'payment_submethod' => 'payment_submethod',
		'first_name' => 'first_name',
		'last_name' => 'last_name',
		'region' => 'state_province',
		'postal' => 'postal_code',
		'address' => 'street_address',
		'city' => 'city',
		'email_address' => 'email',
		'date' => 'date',
		'is_recurring' => 'recurring',
		'utm_source' => 'utm_source',
	];

	/** A minimal set of keys the final donation queue message should have. */
	public const REQUIRED_FIELDS = [
		'gateway_txn_id',
		'gross',
		'currency',
		'country',
		'email',
		'order_id',
		'payment_method',
		'payment_submethod',
		'date',
	];

	public function __construct() {
		parent::__construct();
		$this->addFlag( 'verbose', 'Enable verbose output (show raw matched lines)', 'v' );
		$this->addArgument( 'date', 'Date of contributions to find (e.g. 20250101)' );
		$this->addArgument( 'id', 'Contribution Tracking ID to find (e.g. 1234567890.1)' );
	}

	public function execute() {
		$date = $this->getArgument( 'date' );
		$contributionTrackingId = $this->getArgument( 'id' );

		if ( !is_numeric( $date ) || !is_numeric( $contributionTrackingId ) ) {
			Logger::error( 'Both "date" and "id" arguments must be numeric.' );
			Logger::error( 'Example usage: php BuildDonationMessageFromPaymentLogs.php 20250101 1234567890' );
			return;
		}

		// Search the logs for the given $contributionTrackingId
		$logMatches = $this->runLogSearch( $contributionTrackingId, $date );
		if ( empty( $logMatches ) ) {
			Logger::warning( "No lines found matching contribution ID '$contributionTrackingId' for date '$date'. Exiting." );
			return;
		}

		// Parse out file paths and group log lines
		$logData = $this->structureLogResults( $logMatches );

		// Find and decode JSON strings from log data
		// Note: (the JSON blocks are where all the info is that we can use to build the message)
		$decodedJsonLogData = $this->findAndDecodeJSONStringsFromLogData( $logData );

		// Log how many lines matched
		$lineCount = 0;
		foreach ( $logData as $file => $lines ) {
			$lineCount += count( $lines );
		}

		// If verbose mode was enabled, show the raw lines grouped by file
		if ( $this->getOption( 'verbose' ) ) {
			$this->logVerboseOutput( $logData );
		}

		Logger::info( "Found $lineCount total matching lines. Looking for donation queue message data in them..." );

		// Extract fields from all JSON strings
		$extractedDonationQueueMessageFields = $this->extractOutDonationQueueMessageFields( $decodedJsonLogData );
		if ( empty( $extractedDonationQueueMessageFields ) ) {
			Logger::warning( "No JSON fields of interest were found in the logs. Exiting." );
			return;
		}

		// Build the donation queue message
		$message = $this->buildQueueMessage( $extractedDonationQueueMessageFields );

		// Validate the final message
		if ( !$this->isMessageValid( $message ) ) {
			Logger::error(
				'Message is missing required fields: ' . implode( ', ', self::REQUIRED_FIELDS )
				. ' — Dumping incomplete message and exiting.'
			);
			Logger::error( print_r( $message, true ) );
			return;
		}

		Logger::info( "Constructed donation queue message:\n" . print_r( $message, true ) );

		// Prompt user to push to queue
		echo "\nWould you like to push this message to the donation queue? [y/N]: ";
		$userInput = trim( fgets( STDIN ) ) ?: '';

		if ( strtolower( $userInput ) === 'y' ) {
			try {
				Logger::info( "Pushing message to donation queue..." );
				 QueueWrapper::push( 'donations', $message );
				Logger::info( "Message pushed to donation queue!" );
			} catch ( \Exception $e ) {
				Logger::error( "Failed to push message to queue: " . $e->getMessage() );
			}
		} else {
			Logger::info( "You declined to push the message to the queue." );
		}

		Logger::info( "Done." );
	}

	/**
	 * Run the zgrep command to find matching lines in the logs.
	 *
	 * @param float|int $contributionTrackingId
	 * @param int $date
	 * @return array
	 */
	protected function runLogSearch( float|int $contributionTrackingId, int $date ): array {
		$files = glob( sprintf( self::LOG_FILE_PATH_GREP_PATTERN, $date ) );
		if ( empty( $files ) ) {
			Logger::warning( "No log files matched for date '$date'." );
			return [];
		}

		$combinedOutput = [];
		foreach ( $files as $file ) {
			$command = sprintf( self::ZGREP_COMMAND, $contributionTrackingId, $file );
			Logger::info( "Scanning logfile: $command" );
			exec( escapeshellcmd( $command ), $output, $exitCode );

			// Append the lines from this file to $combinedOutput
			array_push( $combinedOutput, ...$output );
		}

		return $combinedOutput;
	}

	/**
	 * Parse out file paths from the log lines.
	 * Each line is typically in the format:
	 *   /tmp/logs/fundraising-misc-20250330.gz: [log content...]
	 *
	 * We only want to split on the first colon, because the log text itself
	 * can contain additional colons.
	 *
	 * @param array $logData The lines returned by zgrep
	 * @return array $logLines, grouped by file path => [log lines]
	 */
	protected function structureLogResults( array $logData ): array {
		$logLines = [];
		foreach ( $logData as $line ) {
			// Split on the first colon only
			$parts = explode( ':', $line, 2 );
			if ( count( $parts ) < 2 ) {
				// If there's no colon, can't separate file path from the rest
				$filePath = 'unknown';
				$restOfLine = $line;
			} else {
				[ $filePath, $restOfLine ] = $parts;
			}
			$logLines[$filePath][] = $restOfLine;
		}
		return $logLines;
	}

	/**
	 * Detect JSON stings in the log lines, decode and return them.
	 *
	 * @param array $logLines The file => [raw lines] structure
	 * @return array $decodedJsonArrays, grouped by file => [[parsed JSON1, JSON2,...], ...]
	 */
	protected function findAndDecodeJSONStringsFromLogData( array $logLines ): array {
		$decodedJsonArrays = [];

		foreach ( $logLines as $filePath => $lines ) {
			foreach ( $lines as $line ) {
				$decodedJson = $this->findAndDecodeJSONStringsFromLogLine( $line );
				if ( !empty( $decodedJson ) ) {
					$decodedJsonArrays[$filePath][] = $decodedJson;
				}
			}
		}

		return $decodedJsonArrays;
	}

	/**
	 * Attempt to find and decode one or more JSON strings from a log line.
	 * Some logs can have multiple JSON strings on one line, so we do a
	 * match-all with the recursive subroutine regex (?R)
	 * https://www.php.net/manual/en/regexp.reference.recursive.php
	 *
	 * @param string $line
	 *
	 * @return array An array of parsed JSON arrays (each associative)
	 */
	protected function findAndDecodeJSONStringsFromLogLine( string $line ): array {
		$results = [];
		if ( preg_match_all( self::JSON_STRING_REGEX, $line, $matches ) ) {
			foreach ( $matches[0] as $match ) {
				// Remove extra backslashes if present
				$match = stripcslashes( $match );

				// Decode the JSON string
				try {
					$decoded = json_decode( $match, true, 512, JSON_THROW_ON_ERROR );
				} catch ( \JsonException $e ) {
					Logger::warning( "JSON parse error: " . $e->getMessage() );
					continue;
				}

				if ( is_array( $decoded ) ) {
					$results[] = $decoded;
				}
			}
		}
		return $results;
	}

	/**
	 * If the user provided --verbose (or -v), display log lines grouped by file
	 * so they can see exactly which lines matched.
	 *
	 * @param array $logLines
	 * @return void
	 */
	protected function logVerboseOutput( array $logLines ): void {
		Logger::info( "=== Showing matched log lines grouped by file ===" );
		$totalLineCount = 0;

		foreach ( $logLines as $filePath => $lines ) {
			Logger::info( "File: $filePath" );
			foreach ( $lines as $index => $lineContent ) {
				Logger::info( "  [Line $index] $lineContent" );
				$totalLineCount++;
			}
		}
		Logger::info( "Total lines across all files: $totalLineCount" );
		Logger::info( "=== End of log lines output ===" );
	}

	/**
	 * Extract the relevant fields from all parsed JSON strings into a single array.
	 *
	 * $decodedJsonData[filePath] => [
	 *     [ jsonArray1, jsonArray2, ... ], // from line #1
	 *     [ jsonArray3, jsonArray4, ... ], // from line #2
	 * ]
	 *
	 * @param array $decodedJsonLogData
	 *
	 * @return array
	 */
	protected function extractOutDonationQueueMessageFields( array $decodedJsonLogData ): array {
		$extracted = [];

		// lots of arrays of arrays here but bear with it...
		foreach ( $decodedJsonLogData as $filePath => $arrayOfDecodedArrays ) {
			foreach ( $arrayOfDecodedArrays as $decodedJsonArray ) {
				foreach ( $decodedJsonArray as $decodedData ) {
					$this->extractDonationQueueMessageFieldsRecursive( $decodedData, $extracted );
				}
			}
		}
		return $extracted;
	}

	/**
	 * Recursively traverse the parsed JSON, collecting fields according to FIELD_MAP
	 * and any special-case logic (e.g. 'amount' => 'gross').
	 *
	 * @param mixed $data
	 * @param array &$extracted
	 */
	protected function extractDonationQueueMessageFieldsRecursive( $data, array &$extracted ): void {
		if ( !is_array( $data ) ) {
			// Not an array, no further descending
			return;
		}

		// Map direct fields
		foreach ( self::FIELD_MAP as $jsonField => $queueMessageKey ) {
			if ( isset( $data[$jsonField] ) && !isset( $extracted[$queueMessageKey] ) ) {
				$extracted[$queueMessageKey] = $data[$jsonField];
			}
		}

		// Descend further if needed
		foreach ( $data as $subValue ) {
			$this->extractDonationQueueMessageFieldsRecursive( $subValue, $extracted );
		}
	}

	/**
	 * Construct a final donation queue message array from the collected data.
	 *
	 * @param array $extractedFields
	 *
	 * @return array
	 */
	protected function buildQueueMessage( array $extractedFields ): array {
		return [
			'gateway_txn_id' => $extractedFields['gateway_txn_id'] ?? '',
			'response' => false,
			'gateway_account' => 'WikimediaDonations', // we hard code this in our gateway config arrays
			'fee' => 0,
			'gross' => $extractedFields['gross'] ?? '',
			'city' => $extractedFields['city'] ?? '',
			'contribution_tracking_id' => $extractedFields['contribution_tracking_id'] ?? '',
			'country' => $extractedFields['country'] ?? '',
			'currency' => $extractedFields['currency'] ?? '',
			'email' => $extractedFields['email'] ?? '',
			'first_name' => $extractedFields['first_name'] ?? '',
			'gateway' => $extractedFields['gateway'] ?? '',
			'language' => $extractedFields['language'] ?? '',
			'last_name' => $extractedFields['last_name'] ?? '',
			'order_id' => $extractedFields['order_id'] ?? '',
			'payment_method' => $extractedFields['payment_method'] ?? '',
			'payment_submethod' => $extractedFields['payment_submethod'] ?? '',
			'postal_code' => $extractedFields['postal_code'] ?? '',
			'recurring' => $extractedFields['recurring'] ?? '',
			'state_province' => $extractedFields['state_province'] ?? '',
			'street_address' => $extractedFields['street_address'] ?? '',
			'user_ip' => $extractedFields['user_ip'] ?? '',
			'utm_source' => $extractedFields['utm_source'] ?? '',
			'date' => $extractedFields['date'] ?? '',
		];
	}

	/**
	 * Ensure the final message has all REQUIRED_FIELDS.
	 *
	 * @param array $message
	 * @return bool
	 */
	protected function isMessageValid( array $message ): bool {
		foreach ( self::REQUIRED_FIELDS as $requiredField ) {
			if ( empty( $message[$requiredField] ) ) {
				return false;
			}
		}
		return true;
	}
}

$maintClass = BuildDonationMessageFromPaymentLogs::class;
require RUN_MAINTENANCE_IF_MAIN;
