<?php

namespace SmashPig\PaymentProviders\PayPal\Maintenance;

require __DIR__ . '/../../../Maintenance/MaintenanceBase.php';

use SmashPig\Core\Context;
use SmashPig\Maintenance\MaintenanceBase;

/**
 * Test out basic Paypal API connectivity
 */
class TestApi extends MaintenanceBase {

	public function __construct() {
		parent::__construct();
		$this->desiredOptions['config-node']['default'] = 'paypal';
	}

	/**
	 * Do the actual work of the script.
	 */
	public function execute() {
		$providerConfiguration = Context::get()->getProviderConfiguration();
		/** @var \SmashPig\PaymentProviders\PayPal\Api $api */
		$api = $providerConfiguration->object( 'api' );
		$result = $api->makeApiCall( $this->getTestApiParam() );
		print_r( $result );
	}

	/**
	 * Test SetExpressCheckout API call params
	 *
	 * @return array
	 */
	protected function getTestApiParam() {
		$testOrderId = '00000' . rand( 1000, 9999 );
		$params = [
			'VERSION' => 204,
			'METHOD' => 'SetExpressCheckout',
			'RETURNURL' => "https://localhost:9001/index.php?title=Special:PaypalExpressGatewayResult",
			'CANCELURL' => 'https://donate.wikimedia.org/wiki/Ways_to_Give/en',
			'REQCONFIRMSHIPPING' => 0,
			'NOSHIPPING' => 1,
			'LOCALECODE' => 'en_XX',
			'L_PAYMENTREQUEST_0_AMT0' => '30.00',
			'L_PAYMENTREQUEST_0_DESC0' => 'Wikimedia 877 600 9454',
			'PAYMENTREQUEST_0_AMT' => '30.00',
			'PAYMENTREQUEST_0_CURRENCYCODE' => 'USD',
			'PAYMENTREQUEST_0_CUSTOM' => $testOrderId,
			'PAYMENTREQUEST_0_DESC' => 'Wikimedia 877 600 9454',
			'PAYMENTREQUEST_0_INVNUM' => $testOrderId,
			'PAYMENTREQUEST_0_ITEMAMT' => '30.00',
			'PAYMENTREQUEST_0_PAYMENTACTION' => 'Sale',
			'PAYMENTREQUEST_0_PAYMENTREASON' => 'None',
			'SOLUTIONTYPE' => 'Mark'
			];

		return $params;
	}
}

$maintClass = TestApi::class;

require RUN_MAINTENANCE_IF_MAIN;
