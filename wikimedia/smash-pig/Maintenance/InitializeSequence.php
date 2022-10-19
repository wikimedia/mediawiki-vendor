<?php namespace SmashPig\Maintenance;

require 'MaintenanceBase.php';

use SmashPig\Core\Logging\Logger;
use SmashPig\Core\SequenceGenerators\Factory;

/**
 * Script to initialize a sequence generator
 * Example usage: php InitializeSequence.php contribution-tracking 87654321
 *
 * @package SmashPig\Maintenance
 */
class InitializeSequence extends MaintenanceBase {

	public function __construct() {
		parent::__construct();
		$this->addArgument( 'sequence', 'Name of sequence to initialize', true );
		$this->addArgument(
			'start',
			'Number the generator should be initialized to. ' .
			'Note that the next number returned will be one more than this number',
			true
		);
	}

	/**
	 * Do the actual work of the script.
	 */
	public function execute() {
		$sequenceName = $this->getArgument( 'sequence' );
		$start = $this->getArgument( 'start' );
		try {
			$sequence = Factory::getSequenceGenerator( $sequenceName );
		} catch ( \Exception $ex ) {
			$this->error(
				"Could not instantiate sequence generator $sequenceName. Error '" .
				$ex->getMessage() . "'",
				true
			);
		}
		if ( !filter_var( $start, FILTER_VALIDATE_INT ) ) {
			$this->error( 'Second argument must be an integer', true );
		}
		$sequence->initializeSequence( (int)$start );
		Logger::info( "Initialized sequence $sequenceName to $start." );
	}

}

$maintClass = InitializeSequence::class;

require RUN_MAINTENANCE_IF_MAIN;
