<?php namespace SmashPig\Maintenance;

require 'MaintenanceBase.php';

use SmashPig\Core\Logging\Logger;
use SmashPig\Core\SmashPigException;

/**
 * Causes a fatal error and expects an email to be sent out to failmail recipients
 */
class TestFailMail extends MaintenanceBase {

	/**
	 * Do the actual work of the script.
	 */
	public function execute() {
		Logger::info( 'Info log message' );
		Logger::debug( 'Debug log message' );
		Logger::notice( 'Notice...' );
		Logger::getTaggedLogger( 'RawData' )->info( 'This should be tagged RawData' );
		Logger::warning( 'Warning!', [ 'foo' => 'bar' ] );
		Logger::error( 'Error!' );

		try {
			$this->throwException();
		} catch ( SmashPigException $ex ) {
			Logger::alert( 'ALERT!!!!', null, $ex );
		}
	}

	protected function throwException() {
		throw new SmashPigException( 'TestException!' );
	}
}

$maintClass = TestFailMail::class;

require RUN_MAINTENANCE_IF_MAIN;
