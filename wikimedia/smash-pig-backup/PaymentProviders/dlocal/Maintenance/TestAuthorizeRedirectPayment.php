<?php

namespace SmashPig\PaymentProviders\dlocal\Maintenance;

require __DIR__ . '/../../../Maintenance/MaintenanceBase.php';

use SmashPig\Core\Context;
use SmashPig\Maintenance\MaintenanceBase;

/**
 *	A valid response from this authorize will return a rediect_url that you can paste into the browser to
 * complete the payment
 *
 */
class TestAuthorizeRedirectPayment extends MaintenanceBase {

	public function __construct() {
		parent::__construct();
		$this->desiredOptions['config-node']['default'] = 'dlocal';
	}

	public function execute(): void {
		$providerConfiguration = Context::get()->getProviderConfiguration();
		$api = $providerConfiguration->object( 'api' );

		$params = [
			'order_id' => mt_rand(),
			'amount' => '100',
			'currency' => 'USD',
			'country' => 'BR',
			'contact_id' => '12345',
			'state_province' => 'lore',
			'city' => 'lore',
			'postal_code' => 'lore',
			'street_address' => 'lore',
			'street_number' => 2,
			'user_ip' => '127.0.0.1',
			'first_name' => 'Lorem',
			'last_name' => 'Ipsum',
			'email' => 'test@example.com',
			'fiscal_number' => '504.141.611-73',
		];

		$result = $api->redirectPayment( $params );
		print_r( $result );
	}
}

$maintClass = TestAuthorizeRedirectPayment::class;

require RUN_MAINTENANCE_IF_MAIN;
