<?php namespace SmashPig\CrmLink\Messages;

use SmashPig\Core\DataStores\JsonSerializableObject;

/**
 * Message sent once frontend donation processing is completed
 */
class DonationInterfaceFinalStatus extends JsonSerializableObject {
	public $amount;
	public $contribution_tracking_id;
	public $country;
	public $currency_code;
	public $date;
	public $gateway;
	public $gateway_txn_id;
	public $order_id;
	public $payment_method;
	public $payments_final_status;
	public $payment_submethod;
	public $server;
	public $validation_action;
}
