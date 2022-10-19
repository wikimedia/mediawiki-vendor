<?php namespace SmashPig\Maintenance;

require 'MaintenanceBase.php';

use SmashPig\Core\Logging\Logger;

class ExampleScript extends MaintenanceBase {

	public function __construct() {
		parent::__construct();

		// Sample option to add; can be used on the command line with either a:
		// --message "string" or -m "String"
		// See MaintenanceBase::addDefaultParams() for the default options it sets
		$this->addOption( 'message', 'message to print out', 'Hello World!', 'm' );
	}

	/**
	 * Do the actual work of the script.
	 */
	public function execute() {
		// The logger will print out to the console; and any other log streams that have
		// been configured.
		Logger::info( $this->getOption( 'message' ) );

		// Other fun functions to know about:
		// - readConsole() - get input from the console
		// - error() - error out and die with a message
	}

}

$maintClass = ExampleScript::class;

require RUN_MAINTENANCE_IF_MAIN;
