<?php

namespace SmashPig\Maintenance;

use SmashPig\PaymentProviders\Adyen\Audit\AdyenPaymentsAccountingReport;
use SmashPig\PaymentProviders\Adyen\Audit\AdyenSettlementDetailReport;
use SmashPig\PaymentProviders\Braintree\Audit\BraintreeAudit;
use SmashPig\PaymentProviders\dlocal\Audit\DlocalAudit;
use SmashPig\PaymentProviders\Gravy\Audit\GravyAudit;
use SmashPig\PaymentProviders\PayPal\Audit\PayPalAudit;
use SmashPig\PaymentProviders\Trustly\Audit\TrustlyAudit;

require 'MaintenanceBase.php';

class ParseAuditFile extends MaintenanceBase {

	public function __construct() {
		parent::__construct();
		$this->addArgument( 'file', 'path to a file to parse' );
		$this->addFlag( 'isSettlement', 'For Adyen, whether the file is a settlement file', 's' );
	}

	public function execute() {
		$file = $this->getArgument( 'file' );
		$parser = match ( $this->getOption( 'config-node' ) ) {
			'adyen' => $this->getOption( 'isSettlement' ) ?
				new AdyenSettlementDetailReport() :
				new AdyenPaymentsAccountingReport(),
			'braintree' => new BraintreeAudit(),
			'dlocal' => new DlocalAudit(),
			'gravy' => new GravyAudit(),
			'paypal' => new PayPalAudit(),
			'trustly' => new TrustlyAudit(),
			default => throw new \InvalidArgumentException( 'Option "config-node" must be specified.' )
		};
		print_r( $parser->parseFile( $file ) );
	}
}

$maintClass = ParseAuditFile::class;

require RUN_MAINTENANCE_IF_MAIN;
