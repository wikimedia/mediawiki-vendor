<?php namespace SmashPig\Maintenance;

require 'MaintenanceBase.php';

use SmashPig\Core\Logging\Logger;
use SmashPig\Core\QueueConsumers\QueueFileDumper;

/**
 * Script to empty out a queue backing store into a file. Objects are not kept
 * in the queue after dumping.
 *
 * @package SmashPig\Maintenance
 */
class EmptyQueueToDump extends MaintenanceBase {

	protected $datastore = null;

	public function __construct() {
		parent::__construct();
		$this->addOption( 'queue', 'queue name to consume from', 'test' );
		$this->addOption( 'gateway', 'only consume message with this gateway', null );
		$this->addOption( 'max-messages', 'At most consume <n> messages, 0 is infinite', 10, 'm' );
		$this->addOption( 'outfile', 'File to place JSON encoded messages into', 'messages.json', 'f' );
	}

	/**
	 * Do the actual work of the script.
	 */
	public function execute() {
		$outfile = $this->getOption( 'outfile' );
		$conditions = [];
		$gateway = $this->getOption( 'gateway' );
		if ( $gateway ) {
			$conditions['gateway'] = $gateway;
		}

		$consumer = new QueueFileDumper(
			$this->getOption( 'queue' ),
			$this->getOption( 'max-messages' ),
			$outfile,
			$conditions
		);

		$startTime = time();
		$messageCount = $consumer->dequeueMessages();

		$elapsedTime = time() - $startTime;
		Logger::info(
			"Dumped $messageCount messages to $outfile in $elapsedTime seconds."
		);
	}

}

$maintClass = EmptyQueueToDump::class;

require RUN_MAINTENANCE_IF_MAIN;
