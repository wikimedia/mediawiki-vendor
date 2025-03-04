<?php namespace SmashPig\Maintenance;

require 'MaintenanceBase.php';

use SmashPig\Core\DataStores\QueueWrapper;
use SmashPig\Core\Logging\Logger;

/**
 * Script to import a file created by EmptyQueueToDump back into a PHPQueue backend.
 * Handles dumps with one JSON-encoded message body per line.
 *
 * @package SmashPig\Maintenance
 */
class PopulateQueueFromDump extends MaintenanceBase {

	public function __construct() {
		parent::__construct();
		$this->addOption( 'queue', 'queue name to inject into', 'test' );
		$this->addArgument( 'file',
			'File, created by EmptyQueueToDump, with JSON format messages to inject', true );
	}

	/**
	 * Do the actual work of the script.
	 */
	public function execute() {
		$datastore = QueueWrapper::getQueue(
			$this->getOption( 'queue' )
		);

		$startTime = time();
		$messageCount = 0;

		// Open the file for read
		$infile = $this->getArgument( 'file' );
		$f = fopen( $infile, 'r' );
		if ( !$f ) {
			$this->error( "Could not open $infile for read", true );
		}

		// Do the loop!
		while ( ( $line = fgets( $f ) ) !== false ) {
			$message = json_decode( $line, true );
			if ( $message === null ) {
				Logger::error( "Invalid line: $line" );
				continue;
			}

			// if $message SourceFields headers are not set then we send it through QueueWrapper::push()
			if ( !array_key_exists( 'source_enqueued_time', $message ) ) {
				// QueueWrapper::push() injects additional useful properties
				// useful properties declared here \SmashPig\CrmLink\Messages\SourceFields::addToMessage()
				QueueWrapper::push( $this->getOption( 'queue' ), $message );
			} else {

				$datastore->push( $message );
			}

			$messageCount++;
			if ( $messageCount % 1000 == 0 ) {
				print ( '.' );
			}
		}
		print ( '\n' );

		$elapsedTime = time() - $startTime;
		Logger::info(
			"Imported $messageCount messages from $infile in $elapsedTime seconds."
		);
	}
}

$maintClass = PopulateQueueFromDump::class;

require RUN_MAINTENANCE_IF_MAIN;
