<?php

namespace SmashPig\PaymentProviders\PayPal\Maintenance;

require __DIR__ . '/../../../Maintenance/MaintenanceBase.php';

use SmashPig\Maintenance\MaintenanceBase;
use SmashPig\PaymentProviders\PaymentProviderFactory;

/**
 * Test out basic Paypal API connectivity
 */
class SetExpressCheckout extends MaintenanceBase {

	public function __construct() {
		parent::__construct();
		$this->desiredOptions['config-node']['default'] = 'paypal';
		$this->addArgument( 'amount', 'amount' );
		$this->addArgument( 'currency', 'currency' );
		$this->addArgument( 'order_id', 'Order ID' );
		$this->addArgument( 'is_recurring', 'if true, then create recurring token' );
		$this->addArgument( 'locale', 'language locale for cancel url' );
	}

	/**
	 * Do the actual work of the script.
	 */
	public function execute() {
		$provider = PaymentProviderFactory::getProviderForMethod( 'paypal' );
		$params = [
			'amount' => $this->getArgument( 'amount' ),
			'currency' => $this->getArgument( 'currency' ),
			'order_id' => $this->getArgument( 'order_id' ),
			'is_recurring' => $this->getArgument( 'is_recurring' ),
			'locale' => $this->getArgument( 'locale' ),
			'description' => 'Wikimedia 877 600 9454',
			'return_url' => $this->getReturnUrl(),
			'cancel_url' => 'https://donate.wikimedia.org/wiki/Ways_to_Give/' . $this->getArgument( 'locale' ),
		];

		if ( $this->getArgument( 'is_recurring' ) === 1 ) {
			$params['description'] = 'Monthly Subscription';
		}
		$result = $provider->createPaymentSession( $params );
		print_r( $result );
	}

	/**
	 * @return string
	 */
	private function getReturnUrl(): string {
		$url = "https://payments.wikimedia.org/index.php?title=Special:PaypalExpressGatewayResult";
		$url .= '&order_id=' . $this->getArgument( 'order_id' );
		if ( $this->getArgument( 'is_recurring' ) === 1 ) {
			$url .= '&recurring=1';
		}
		return $url;
	}
}

$maintClass = SetExpressCheckout::class;

require RUN_MAINTENANCE_IF_MAIN;
