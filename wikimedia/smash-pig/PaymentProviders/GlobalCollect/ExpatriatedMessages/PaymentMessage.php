<?php namespace SmashPig\PaymentProviders\GlobalCollect\ExpatriatedMessages;

class PaymentMessage extends GlobalCollectMessage {
	protected $additional_reference;
	protected $amount;
	protected $attempt_id;
	protected $currency_code;
	protected $effort_id;
	protected $gateway_account;
	protected $order_id;
	protected $payment_method_id;
	protected $payment_product_id;
	protected $payment_reference;
	protected $received_date;
	protected $status_date;
	protected $status_id;

	protected $fields = [
		'additional_reference',
		'amount' => [ 'map' => 'gross' ],
		'attempt_id',
		'currency_code' => [ 'map' => 'currency' ],
		'effort_id',
		'gateway_account',
		'order_id' => [ 'map' => 'gateway_txn_id' ],
		'payment_method_id',
		'payment_product_id' => [ 'map' => 'payment_product' ],
		'payment_reference',
		'received_date' => [ 'map' => 'date' ],
		'status_date',
		'status_id',
	];

	public function getDestinationQueue() {
		// XXX
		return 'donations';
	}
}
