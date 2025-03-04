<?php

namespace SmashPig\PaymentProviders\dlocal\Maintenance;

require __DIR__ . '/../../../Maintenance/MaintenanceBase.php';
use SmashPig\Core\Logging\Logger;
use SmashPig\Maintenance\MaintenanceBase;
use SmashPig\PaymentProviders\dlocal\PaymentProvider;
use SmashPig\PaymentProviders\PaymentProviderFactory;

/**
 *	Charge a recurring UPI payment in dlocal india IR method
 */
class ChargeRecurringIR extends MaintenanceBase {

	public function __construct() {
		parent::__construct();
		$this->desiredOptions['config-node']['default'] = 'dlocal';
		$this->addArgument( 'id', 'Gateway Txn ID (gateway_tnx_id)' );
		$this->addArgument( 'order_id', 'Order ID' );
		$this->addArgument( 'amount', 'recurring amount could be any less than 5k' );
		$this->addArgument( 'token', 'if null get it from payment detail based on txn id, otherwise use it', false );
		$this->addArgument( 'email', 'Email', false );
		$this->addArgument( 'first_name', 'First Name', false );
		$this->addArgument( 'last_name', 'Last Name', false );
		$this->addArgument( 'fiscal_number', 'Fiscal Number', false );
	}

	public function execute(): void {
		/** @var PaymentProvider $paymentProvider */
		$paymentProvider = PaymentProviderFactory::getProviderForMethod( 'bt' );
		$gateway_txn_id = $this->getArgument( 'id' );
		$token = $this->getArgument( 'token' );
		$amount = $this->getArgument( 'amount' );
		if ( $token ) {
			Logger::info( "use token from ipn: " . $token );
		} else {
			$paymentDetailResponse = $paymentProvider->getPaymentDetail( $gateway_txn_id );
			$paymentDetail = $paymentDetailResponse->getRawResponse();
			$token = $paymentDetail['wallet']['token'];
			Logger::info( "Got token from payment detail: " . $token );
		}
		$params = [
			'amount' => $amount,
			'currency' => 'INR',
			'country' => 'IN',
			'order_id' => $this->getArgument( 'order_id' ),
			'first_name' => $this->getArgument( 'first_name' ) ?? 'test',
			'last_name' => $this->getArgument( 'last_name' ) ?? 'test',
			'description' => 'test IR recurring payment subscription',
			'email' => $this->getArgument( 'email' ) ?? 'email',
			'payment_submethod' => 'upi',
			'fiscal_number' => $this->getArgument( 'fiscal_number' ),
			'recurring' => 1,
			'recurring_payment_token' => $token,
		];
		$chargeRecurringPaymentResponse = $paymentProvider->createPayment( $params );
		Logger::info( "Charge recurring payment result: " . print_r( $chargeRecurringPaymentResponse, true ) );
	}
}

$maintClass = ChargeRecurringIR::class;

require RUN_MAINTENANCE_IF_MAIN;
