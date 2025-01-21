<?php

namespace SmashPig\PaymentProviders\Ingenico\Tests\Manual;

require __DIR__ . '/../../../../Maintenance/MaintenanceBase.php';

use SmashPig\Core\Logging\Logger;
use SmashPig\Core\SmashPigException;
use SmashPig\Maintenance\MaintenanceBase;
use SmashPig\PaymentProviders\PaymentProviderFactory;

/**
 * Test the creation of a new payment using an existing payment token.
 *
 * @package SmashPig\PaymentProviders\Ingenico\Tests\Manual
 */
class TestCreatePayment extends MaintenanceBase {

	public function __construct() {
		parent::__construct();

		$this->addArgument(
			'token',
			'ingenico recurring payment token',
			false
		);

		$this->addArgument(
			'amount',
			'payment amount to be requested',
			false
		);

		$this->desiredOptions['config-node']['default'] = 'ingenico';
	}

	public function execute() {
		Logger::debug( 'Starting Ingenico createPayment test' );

		try {

			$token = $this->getArgument( 'token',
				'229a1d6e-1b26-4c91-8e00-969a49c9d041' );
			$amount = $this->getArgument( 'amount', 35 );

			$params = [
				'recurring' => true,
				'installment' => 'recurring',
				'recurring_payment_token' => $token,
				'amount' => $amount,
				'currency' => 'USD',
				'descriptor' => 'Wikimedia Foundation - Recurring donation',
				'order_id' => mt_rand(),
			];

			$paymentMethod = 'cc';
			/** @var \SmashPig\PaymentProviders\Ingenico\PaymentProvider $paymentProvider */
			$paymentProvider = PaymentProviderFactory::getProviderForMethod( $paymentMethod );
			$response = $paymentProvider->createPayment( $params );

			/** @var \SmashPig\Core\Logging\TaggedLogger $taggedLogger */
			Logger::debug( 'Completed Ingenico createPayment test', $response );
			print json_encode( $response, JSON_PRETTY_PRINT ) . PHP_EOL;

		} catch ( SmashPigException $ex ) {
			Logger::error( 'Error in Ingenico createPayment test', null, $ex );
		}
	}
}

$maintClass = TestCreatePayment::class;

require RUN_MAINTENANCE_IF_MAIN;
