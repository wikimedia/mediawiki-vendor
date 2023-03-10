<?php

namespace SmashPig\PaymentProviders\dlocal\Maintenance;

require __DIR__ . '/../../../Maintenance/MaintenanceBase.php';

use SmashPig\Core\Logging\Logger;
use SmashPig\Maintenance\MaintenanceBase;
use SmashPig\PaymentProviders\dlocal\PaymentProvider;
use SmashPig\PaymentProviders\PaymentProviderFactory;

/**
 *	Create a recurring UPI payment in dlocal india IR method
 */
class CreateIRRecurringSubscription extends MaintenanceBase {

	public function __construct() {
		parent::__construct();
		$this->desiredOptions['config-node']['default'] = 'dlocal';
		$this->addArgument( 'id', 'Order ID (order_id)', true );
		$this->addArgument( 'amount', 'Recurring amount', true );
	}

	public function execute(): void {
		/** @var PaymentProvider $paymentProvider */
		$paymentProvider = PaymentProviderFactory::getProviderForMethod( 'bt' );
		$order_id = $this->getArgument( 'id' );
		$amount = $this->getArgument( 'amount' );
		$params = [
			'amount' => $amount,
			'currency' => 'INR',
			'country' => 'IN',
			'order_id' => $order_id,
			'first_name' => 'test',
			'last_name' => 'test',
			'payment_method_id' => 'IR',
			'description' => 'test IR recurring payment subscription',
			'email' => 'test@test.com',
			'fiscal_number' => 'AAAAA9999C',
			'recurring' => 1,
		];
		$creatPaymentRecurringSubscriptionResponse = $paymentProvider->createPayment( $params );
		Logger::info( "Create payment result: " . print_r( $creatPaymentRecurringSubscriptionResponse, true ) );
	}
}

$maintClass = CreateIRRecurringSubscription::class;

require RUN_MAINTENANCE_IF_MAIN;
