<?php
namespace SmashPig\Maintenance;

require 'MaintenanceBase.php';

use SmashPig\Core\DataStores\PendingDatabase;
use SmashPig\Core\Logging\Logger;

/**
 * Reads messages from the pending db and creates test IPN message files
 * Amazon:
 *  php Maintenance/CreateIpnMessagesFromPendingDb.php amazon
 *  PaymentProvider/Amazon/Tests/inject.ph payments-listener.local.wmftest.net '/smashpig_http_handler.php?p=amazon/listener' CaptureCompleted.10-1.json
 */
class CreateIpnMessagesFromPendingDb extends MaintenanceBase {

	/**
	 * @var PendingDatabase
	 */
	protected $pendingDatabase;

	protected $templateDir;

	public function __construct() {
		parent::__construct();
		$this->addArgument( 'gateway', 'Create IPN messages for which gateway', true );
		$this->addOption( 'message-limit', 'At most create <n> messages', 10, 'm' );
		$this->addOption( 'output-dir', 'Write messages to this directory', getcwd(), 'o' );
	}

	/**
	 * Do the actual work of the script.
	 */
	public function execute() {
		$pendingDatabase = PendingDatabase::get();

		$numCreated = 0;
		$limit = $this->getOptionOrConfig( 'message-limit', 'maintenance/create-ipns/message-limit' );
		$output = $this->getOption( 'output-dir' );
		$gateway = $this->getArgument( 'gateway' );
		$pendingMessages = $pendingDatabase->fetchMessagesByGatewayNewest( $gateway, $limit );
		if ( !$pendingMessages ) {
			Logger::info( "No pending database entries found for $gateway" );
			return;
		}
		$this->templateDir = __DIR__ . '/../Tests/IPNTemplates/' . $gateway . '/';
		$templates = scandir( $this->templateDir );
		foreach ( $pendingMessages as $pendingMessage ) {
			$this->createIpnMessages(
				$pendingMessage,
				$templates,
				$output
			);
			$numCreated++;
		}

		Logger::info(
			"Created $numCreated (sets of) IPN messages."
		);
	}

	protected function createIpnMessages( $pendingMessage, $templates, $outputDir ) {
		$oid = $pendingMessage['order_id'];
		$replacements = [
			'[[CURRENCY]]' => $pendingMessage['currency'],
			'[[AMOUNT]]' => $pendingMessage['gross'],
			// FIXME yen?
			'[[AMOUNT_IN_CENTS]]' => floatval( $pendingMessage['gross'] ) * 100,
			'[[ORDER_ID]]' => $oid,
			'[[PROCESSOR_REF_1]]' => mt_rand(),
			'[[PROCESSOR_REF_2]]' => mt_rand(),
		];
		if ( isset( $pendingMessage['gateway_account'] ) ) {
			$replacements['[[ACCOUNT_CODE]]'] = $pendingMessage['gateway_account'];
		}
		foreach ( $templates as $template ) {
			$fullPath = $this->templateDir . $template;
			if ( is_dir( $fullPath ) ) {
				continue;
			}
			$contents = file_get_contents( $fullPath );
			$fname = $outputDir . '/' . preg_replace(
				'/(.[a-z0-9]+)$/i',
				'.' . $oid . '\1',
				$template
			);
			foreach ( $replacements as $search => $replace ) {
				$contents = str_replace( $search, $replace, $contents );
			}
			file_put_contents( $fname, $contents );
			Logger::debug( "Wrote $fname." );
		}
	}
}

$maintClass = CreateIpnMessagesFromPendingDb::class;

require RUN_MAINTENANCE_IF_MAIN;
