<?php

namespace SmashPig\PaymentProviders\PayPal\Maintenance;

require __DIR__ . '/../../../Maintenance/MaintenanceBase.php';

use SmashPig\Maintenance\MaintenanceBase;
use SmashPig\PaymentProviders\PaymentProviderFactory;

$maintClass = 'SmashPig\PaymentProviders\PayPal\Maintenance\CreateRecurringPaymentsProfile';

/**
 * Create Recurring Payments Profile
 */
class CreateRecurringPaymentsProfile extends MaintenanceBase {

	public function __construct() {
		parent::__construct();
		$this->desiredOptions['config-node']['default'] = 'paypal';
		$this->addArgument( 'gateway_session_id', 'Gateway session ID / PayPal EC TOKEN', true );
		$this->addArgument( 'order_id', 'Order ID / PROFILEREFERENCE', true );
		$this->addArgument( 'amount', 'Amount', true );
		$this->addArgument( 'currency', 'Currency', true );
		$this->addArgument( 'email', 'Email', true );
		$this->addArgument( 'date', 'Start Date', true ); // for monthly convert, we would want to start the donation one month in the future
	}

	/**
	 * Do the actual work of the script.
	 */
	public function execute() {
		$provider = PaymentProviderFactory::getProviderForMethod( 'paypal' );
		$result = $provider->createRecurringPaymentsProfile( [
			'gateway_session_id' => $this->getArgument( 'gateway_session_id' ),
			'order_id' => $this->getArgument( 'order_id' ),
			'amount' => $this->getArgument( 'amount' ),
			'currency' => $this->getArgument( 'currency' ),
			'email' => $this->getArgument( 'email' ),
			'date' => $this->getArgument( 'date' ),
			'description' => 'Monthly Subscription' // need match the L_BILLINGAGREEMENTDESCRIPTION from SetExpressCheckout
		] );
		print_r( $result );
	}
}

require RUN_MAINTENANCE_IF_MAIN;
