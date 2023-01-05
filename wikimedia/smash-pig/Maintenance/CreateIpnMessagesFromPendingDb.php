<?php
namespace SmashPig\Maintenance;

require 'MaintenanceBase.php';

use SmashPig\Core\Context;
use SmashPig\Core\DataStores\PendingDatabase;
use SmashPig\Core\Logging\Logger;

/**
 * Reads messages from the pending db and creates test IPN message files
 * Adyen:
 *  php Maintenance/CreateIpnMessagesFromPendingDb.php adyen
 *  Tests/SoapInjector/soapinject.py payments-listener.local.wmftest.net '/smashpig_http_handler.php?p=adyen/listener' auth_success.10.1.xml
 * Amazon:
 *  php Maintenance/CreateIpnMessagesFromPendingDb.php amazon
 *  PaymentProvider/Amazon/Tests/inject.ph payments-listener.local.wmftest.net '/smashpig_http_handler.php?p=amazon/listener' CaptureCompleted.10-1.json
 * AstroPay:
 *  php Maintenance/CreateIpnMessagesFromPendingDb.php --config-node astropay astropay
 *  curl -d @success.10.1.curl http://payments-listener.local.wmftest.net/smashpig_http_handler.php?p=astropay/listener
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
		$this->addOption( 'max-messages', 'At most create <n> messages', 10, 'm' );
		$this->addOption( 'output-dir', 'Write messages to this directory', getcwd(), 'o' );
	}

	/**
	 * Do the actual work of the script.
	 */
	public function execute() {
		$pendingDatabase = PendingDatabase::get();

		$numCreated = 0;
		$limit = $this->getOptionOrConfig( 'max-messages', 'maintenance/create-ipns/message-limit' );
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
		if ( $this->getArgument( 'gateway' ) === 'astropay' ) {
			// ugly, but whatchagonnado?
			$replacements['[[ASTROPAY_SIGNATURE_SUCCESS]]'] = $this->getAstroPaySignature( $pendingMessage, '9' );
			$replacements['[[ASTROPAY_SIGNATURE_FAILURE]]'] = $this->getAstroPaySignature( $pendingMessage, '8' );
		}
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

	protected function getAstroPaySignature( $pendingMessage, $result ) {
		$c = Context::get()->getProviderConfiguration();
		$login = $c->val( 'login' );
		$secret = $c->val( 'secret' );
		$signed = $login . $result . $pendingMessage['gross'] . $pendingMessage['order_id'];
		return strtoupper(
			hash_hmac( 'sha256', pack( 'A*', $signed ), pack( 'A*', $secret ) )
		);
	}

}

$maintClass = CreateIpnMessagesFromPendingDb::class;

require RUN_MAINTENANCE_IF_MAIN;
