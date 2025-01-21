<?php

namespace SmashPig\PaymentProviders\PayPal\Maintenance;

require __DIR__ . '/../../../Maintenance/MaintenanceBase.php';

use SmashPig\Maintenance\MaintenanceBase;
use SmashPig\PaymentProviders\PaymentProviderFactory;

$maintClass = 'SmashPig\PaymentProviders\PayPal\Maintenance\DoExpressCheckoutPayment';

class DoExpressCheckoutPayment extends MaintenanceBase {

	public function __construct() {
		parent::__construct();
		$this->desiredOptions['config-node']['default'] = 'paypal';
		$this->addArgument( 'gateway_session_id', 'Gateway session ID / PayPal TOKEN' );
		$this->addArgument( 'payerID', 'Processor Contact ID / PayPal PAYERID' );
		$this->addArgument( 'order_id', 'Order ID / PayPal PAYMENTREQUEST_0_INVNUM' );
		$this->addArgument( 'amount', 'Amount / PayPal PAYMENTREQUEST_0_AMT' );
	}

	/**
	 * Do the actual work of the script.
	 */
	public function execute() {
		$provider = PaymentProviderFactory::getProviderForMethod( 'paypal' );
		$result = $provider->approvePayment( [
			'gateway_session_id' => $this->getArgument( 'gateway_session_id' ),
			'processor_contact_id' => $this->getArgument( 'payerID' ),
			'order_id' => $this->getArgument( 'order_id' ),
			'amount' => $this->getArgument( 'amount' ),
			'currency' => 'USD',
			'description' => 'test DoExpressCheckouPayment'
		] );
		print_r( $result );
	}
}

require RUN_MAINTENANCE_IF_MAIN;
