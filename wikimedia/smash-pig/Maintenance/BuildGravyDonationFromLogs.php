<?php

namespace SmashPig\Maintenance;

require 'MaintenanceBase.php';

use SmashPig\Core\DataStores\QueueWrapper;
use SmashPig\Core\Logging\Logger;
use SmashPig\PaymentProviders\Gravy\GravyHelper;
use SmashPig\PaymentProviders\Gravy\ReferenceData;

/**
 * Maintenance script to search logs, extract JSON strings,
 * build a gravy donation queue message, and optionally push it to a queue.
 *
 * Useful for backfilling or correcting missed gravy donation messages.
 * See: T381012
 *
 * To test locally:
 *
 * 1. Create logs directory in docker container:
 *    docker compose exec -u0 smashpig mkdir -p /srv/archive/frlog
 *
 * 2. Copy sample payment logs to container's temp dir:
 *    docker compose cp /path/to/logs smashpig:/tmp/
 *
 * 3. Symlink logs directory:
 *    docker compose exec -u0 smashpig ln -s /tmp/logs /srv/archive/frlog/logs
 *
 * 4. Run script:
 *    ./scripts/smashpig.sh
 *    php Maintenance/BuildGravyDonationFromLogs.php <YYYYMMDD> <contribution_tracking_id>
 *
 * Example:
 *    php Maintenance/BuildGravyDonationFromLogs.php 20250101 1234567890.1
 */
class BuildGravyDonationFromLogs extends MaintenanceBase {

	public const LOG_FILES_PATH = '/srv/archive/frlog/logs/*%s.gz';
	public const ZGREP_COMMAND = '/bin/zgrep -H -i %s %s';

	/**
	 * Regex to capture a JSON string with possible nested JSON strings.
	 */
	public const JSON_STRING_REGEX = '/\{(?:(?>[^{}]+)|(?R))*}/';

	/**
	 * Mapping of gravy log data to donation queue message keys.
	 */
	public const GRAVY_FIELD_TO_QUEUE_MESSAGE_MAP = [
		'ip_address' => 'user_ip',
		'id' => 'gateway_txn_id',
		'external_identifier' => 'contribution_tracking_id',
		'currency' => 'currency',
		'country' => 'country',
		'accept_language' => 'language',
		'amount' => 'gross',
		'payment_method' => 'payment_method',
		'payment_submethod' => 'payment_submethod',
		'payment_service' => 'backend_processor',
		'payment_service_transaction_id' => 'backend_processor_txn_id',
		'reconciliation_id' => 'payment_orchestrator_reconciliation_id',
		'first_name' => 'first_name',
		'last_name' => 'last_name',
		'state' => 'state_province',
		'postal' => 'postal_code',
		'line1' => 'street_address',
		'city' => 'city',
		'email_address' => 'email',
		'created_at' => 'date',
		'recurringProcessingModel' => 'recurring',
		'utm_source' => 'utm_source',
	];

	public const REQUIRED_QUEUE_MESSAGE_FIELDS = [
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
			Logger::info( 'Both "date" and "id" arguments must be numeric.' );
			Logger::info( 'Example usage: php BuildDonationMessageFromPaymentLogs.php 20250101 1234567890' );
			return;
		}

		$logSearchMatches = $this->runLogSearch( $contributionTrackingId, $date );
		if ( empty( $logSearchMatches ) ) {
			Logger::info( "No lines found matching contribution ID '$contributionTrackingId' for date '$date'. Exiting." );
			return;
		}

		$structuredLogData = $this->structureLogResults( $logSearchMatches );
		$this->logMatchingLinesCount( $structuredLogData );

		$decodedJsonLogData = $this->findAndDecodeJSONStringsFromLogData( $structuredLogData );

		if ( $this->getOption( 'verbose' ) ) {
			$this->logVerboseOutput( $structuredLogData );
		}

		$extractedDonationQueueMessageFields = $this->extractOutDonationQueueMessageFields( $decodedJsonLogData );
		if ( empty( $extractedDonationQueueMessageFields ) ) {
			Logger::info( "No matching fields were found in the logs. Exiting." );
			return;
		}

		// Build the donation queue message
		$message = $this->buildQueueMessage( $extractedDonationQueueMessageFields );

		// Validate the final message
		$validationResult = $this->isMessageValid( $message );
		if ( $validationResult !== true ) {
			$invalidField = $validationResult;
			Logger::info(
				"Message is missing required field '$invalidField' — Dumping incomplete message and exiting."
			);
			Logger::info( print_r( $message, true ) );
			return;
		} else {
			Logger::info( "Message data is valid. Building message" );
		}

		Logger::info( "Built donation queue message:\n" . print_r( $message, true ) );
		return $this->promptAndPushToQueue( $message );
	}

	/**
	 * Executes a log search by running a zgrep command for a specific contribution tracking ID
	 * across log files for a given date.
	 *
	 * @param float|int $contributionTrackingId The ID used to filter log entries.
	 * @param int $date The date in 'YYYYMMDD' format used to locate the relevant log files.
	 *
	 * @return array An array of log lines that match the search criteria.
	 */
	protected function runLogSearch( float|int $contributionTrackingId, int $date ): array {
		$files = glob( sprintf( self::LOG_FILES_PATH, $date ) );
		if ( empty( $files ) ) {
			Logger::info( "No log files matched for date '$date'." );
			return [];
		}

		$combinedOutput = [];
		foreach ( $files as $file ) {
			$command = sprintf( self::ZGREP_COMMAND, $contributionTrackingId, $file );
			Logger::info( "Scanning logfile: $command" );
			exec( escapeshellcmd( $command ), $output, $exitCode );
			array_push( $combinedOutput, ...$output );
		}

		return $combinedOutput;
	}

	/**
	 * Processes log data into a structured format, grouping log entries by file path.
	 *
	 * @param array $logData An array of log lines to be structured.
	 * @return array An associative array where keys are file paths and values are log lines.
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
	 * Logs the total count of matched lines.
	 *
	 * @param array $structuredLogData
	 * @return void
	 */
	protected function logMatchingLinesCount( array $structuredLogData ): void {
		$lineCount = 0;
		foreach ( $structuredLogData as $file => $lines ) {
			$lineCount += count( $lines );
		}
		Logger::info( "Found $lineCount total matching lines. Looking for donation queue message data in them..." );
	}

	/**
	 * Detect JSON stings in the log lines, decode, and return them.
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
	 * match-all with the recursive regex (?R)
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
				// Remove \x09 characters from minfraud log lines
				$match = str_replace( "\x09", '', $match );
				// Decode the JSON string
				try {
					$decoded = json_decode( $match, true, 512, JSON_THROW_ON_ERROR );
				} catch ( \JsonException $e ) {
					Logger::info( "JSON parse error: " . $e->getMessage() );
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
				Logger::info( "[$filePath] $lineContent" );
				$totalLineCount++;
			}
		}
		Logger::info( "Total lines across all files: $totalLineCount" );
		Logger::info( "=== End of log lines output ===" );
	}

	/**
	 * Extracts fields from the JSON decoded log data.
	 *
	 * @param array $decodedJsonLogData
	 * @return array An array of extracted fields from the donation queue messages.
	 */
	protected function extractOutDonationQueueMessageFields( array $decodedJsonLogData ): array {
		$extractedFields = [];
		foreach ( $decodedJsonLogData as $filePath => $logEntries ) {
			foreach ( $logEntries as $jsonEntry ) {
				foreach ( $jsonEntry as $donationData ) {
					$this->extractDonationQueueMessageFieldsRecursive( $donationData, $filePath, $extractedFields );
				}
			}
		}
		return $extractedFields;
	}

	/**
	 * Traverse the decoded JSON, collecting fields according to GRAVY_LOG_FIELD_TO_QUEUE_MESSAGE_MAP
	 * and any special-case logic (e.g. 'accept_language' => 'language').
	 *
	 * @param mixed $data
	 * @param string $filePath
	 * @param array &$extracted
	 */
	protected function extractDonationQueueMessageFieldsRecursive( mixed $data, string $filePath, array &$extracted ): void {
		if ( !is_array( $data ) ) {
			// Not an array, no further descending
			return;
		}

		// Map direct fields
		foreach ( self::GRAVY_FIELD_TO_QUEUE_MESSAGE_MAP as $gravyField => $queueMessageKey ) {
			if ( isset( $data[$gravyField] ) && !isset( $extracted[$queueMessageKey] ) ) {
				switch ( $gravyField ) {
					case 'id':
						// Skip looking for transaction IDs in the fraud logs as maxmind data contains conflicting keys
						$isNotFraudLogFile = !str_contains( strtolower( $filePath ), 'fraud' );
						if ( $isNotFraudLogFile ) {
							$extracted[$queueMessageKey] = $data[$gravyField];
						}
						break;
					case 'payment_method':
						if ( is_array( $data[$gravyField] ) ) {
							// Note: we only process this when $data[payment_method] is an array.
							// Set payment method and submethod from the payment_method data
							if ( isset( $data[$gravyField]['method'] ) ) {
								$methodData = ReferenceData::decodePaymentMethod(
									$data[$gravyField]['method'],
									$data[$gravyField]['scheme'] ?? ''
								);
								$extracted['payment_method'] = $methodData[0];
								$extracted['payment_submethod'] = $methodData[1];
							}
							// When this is set, it contains the recurring payment token
							if ( isset( $data[$gravyField]['id'] ) ) {
								$extracted['recurring_payment_token'] = $data[$gravyField]['id'];
							}
						}
						break;
					case 'payment_service':
						if ( is_array( $data[$gravyField] ) && isset( $data[$gravyField]['payment_service_definition_id'] ) ) {
							$extracted[$queueMessageKey] = GravyHelper::extractProcessorNameFromServiceDefinitionId( $data[$gravyField]['payment_service_definition_id'] );
						}
						break;

					case 'external_identifier':
						$extracted['order_id'] = $data[$gravyField];
						// Remove the trailing ".$sequence" for the contribution tracking ID
						$extracted['contribution_tracking_id'] = preg_replace( '/\.\d+$/', '', $data[$gravyField] );
						break;

					case 'created_at':
						$extracted[$queueMessageKey] = strtotime( $data[$gravyField] );
						break;
					case 'recurringProcessingModel':
						if ( $data['recurringProcessingModel'] === 'Subscription' ) {
							$extracted[$queueMessageKey] = '1';
						}
						break;
					case 'accept_language':
						// Map 'accept_language' to 'language' and extract primary language code
						$languageString = $data[$gravyField];
						$languageCode = explode( ',', $languageString )[0];
						$primaryLanguage = explode( '-', $languageCode )[0];
						$extracted[$queueMessageKey] = $primaryLanguage;
						break;

					default:
						$extracted[$queueMessageKey] = $data[$gravyField];
						break;
				}
			}
		}

		// Descend further if needed
		foreach ( $data as $subValue ) {
			$this->extractDonationQueueMessageFieldsRecursive( $subValue, $filePath, $extracted );
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
			// Transaction identifiers
			'gateway_txn_id' => $extractedFields['gateway_txn_id'] ?? '',
			'order_id' => $extractedFields['order_id'] ?? '',
			'contribution_tracking_id' => $extractedFields['contribution_tracking_id'] ?? '',
			'backend_processor' => $extractedFields['backend_processor'] ?? '',
			'backend_processor_txn_id' => $extractedFields['backend_processor_txn_id'] ?? '',
			'payment_orchestrator_reconciliation_id' => $extractedFields['payment_orchestrator_reconciliation_id'] ?? '',

			// Payment details
			'response' => false,
			'gateway_account' => 'WikimediaDonations', // we hard code this in our gateway config arrays
			'gateway' => 'gravy', // we hard code this since we're only using this for gravy
			'fee' => 0,
			'gross' => $extractedFields['gross'] ?? '',
			'currency' => $extractedFields['currency'] ?? '',
			'payment_method' => $extractedFields['payment_method'] ?? '',
			'payment_submethod' => $extractedFields['payment_submethod'] ?? '',
			'recurring' => $extractedFields['recurring'] ?? '',
			'recurring_payment_token' => $extractedFields['recurring_payment_token'] ?? '',

			// Donor information
			'first_name' => $extractedFields['first_name'] ?? '',
			'last_name' => $extractedFields['last_name'] ?? '',
			'email' => $extractedFields['email'] ?? '',
			'language' => $extractedFields['language'] ?? '',
			'user_ip' => $extractedFields['user_ip'] ?? '',

			// Address details
			'street_address' => $extractedFields['street_address'] ?? '',
			'city' => $extractedFields['city'] ?? '',
			'state_province' => $extractedFields['state_province'] ?? '',
			'postal_code' => $extractedFields['postal_code'] ?? '',
			'country' => $extractedFields['country'] ?? '',

			// Metadata
			'utm_source' => $extractedFields['utm_source'] ?? '',
			'date' => $extractedFields['date'] ?? '',
		];
	}

	/**
	 * Validates if the given message contains all required fields.
	 *
	 * Checks whether all the fields defined in REQUIRED_FIELDS are present
	 * and not empty within the provided message array.
	 *
	 * @param array $message The message data to be validated
	 * @return string|true Returns true if valid, otherwise returns name of missing field
	 */
	protected function isMessageValid( array $message ): string|bool {
		foreach ( self::REQUIRED_QUEUE_MESSAGE_FIELDS as $requiredField ) {
			if ( empty( $message[$requiredField] ) ) {
				return $requiredField;
			}
		}
		// Add recurring token check for recurring messages
		if ( isset( $message['recurring'] ) && $message['recurring'] === '1'
			&& empty( $message['recurring_payment_token'] ) ) {
			return 'recurring_payment_token';
		}
		return true;
	}

	/**
	 * Prompts the user to confirm pushing a message to the donation queue.
	 *
	 * @param array $message The message to push to the queue
	 * @return bool Whether the message was successfully pushed
	 */
	protected function promptAndPushToQueue( array $message ): bool {
		echo "\nWould you like to push this message to the donation queue? [y/N]: ";
		$userInput = trim( fgets( STDIN ) ) ?: '';

		if ( strtolower( $userInput ) === 'y' ) {
			try {
				Logger::info( "Pushing message to donation queue..." );
				QueueWrapper::push( 'donations', $message );
				Logger::info( "Message pushed to donation queue!" );
				return true;
			} catch ( \Exception $e ) {
				Logger::info( "Failed to push message to queue: " . $e->getMessage() );
				return false;
			}
		} else {
			Logger::info( "You declined to push the message to the queue." );
			return false;
		}
	}
}

$maintClass = BuildGravyDonationFromLogs::class;
require RUN_MAINTENANCE_IF_MAIN;
