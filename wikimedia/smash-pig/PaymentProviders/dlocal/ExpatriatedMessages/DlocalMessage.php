<?php namespace SmashPig\PaymentProviders\dlocal\ExpatriatedMessages;

use SmashPig\Core\Messages\ListenerMessage;
use SmashPig\PaymentProviders\dlocal\ReferenceData;

abstract class DlocalMessage extends ListenerMessage {

	/**
	 * @var array
	 */
	protected $fields = [
		'id',
		'type',
		'event_info',
		'amount',
		'status',
		'status_detail',
		'status_code',
		'currency',
		'country',
		'payment_method_id',
		'payment_method_type',
		'payment_method_flow',
		'payer',
		'user_reference',
		'email',
		'document',
		'order_id',
		'description',
		'notification_url',
		'callback_url',
		'created_date',
		'authorization',
		'signatureInput',
		'wallet'
	];

	/**
	 * @var mixed
	 */
	public $authorization;

	/**
	 * @var mixed
	 */
	public $signatureInput;

	/**
	 * @var mixed
	 */
	protected $id;

	/**
	 * @var mixed
	 */
	protected $order_id;

	/**
	 * @var mixed
	 */
	protected $user_reference;

	/**
	 * @var mixed
	 */
	protected $description;

	/**
	 * @var mixed
	 */
	protected $document;

	/**
	 * @var string
	 */
	protected $payment_method_id;

	/**
	 * @var string
	 */
	protected $payment_method_type;

	/**
	 * @var mixed
	 */
	protected $payment_method_flow;

	/**
	 * @var mixed
	 */
	protected $amount;

	/**
	 * @var mixed
	 */
	protected $currency;

	/**
	 * @var mixed
	 */
	protected $country;

	/**
	 * @var array
	 */
	protected $payer;

	/**
	 * @var mixed
	 */
	protected $created_date;

	/**
	 * @var mixed
	 */
	protected $status;

	/**
	 * @var array
	 */
	protected $wallet;

	public function validate(): bool {
		return true;
	}

	public function constructFromValues( array $values ) {
		foreach ( $this->fields as $key ) {
			$this->$key = ( array_key_exists( $key, $values ) ? $values[$key] : '' );
		}
	}

	abstract public function getDestinationQueue();

	/**
	 * Map dlocal's fields to ours
	 *
	 * @return array $queueMsg
	 */
	public function normalizeForQueue() {
		// Normalize the payment method and submethod
		[ $method, $submethod ] = ReferenceData::decodePaymentMethod(
			$this->payment_method_type,
			$this->payment_method_id
		);

		// Get just the contribution_tracking_id from the order_id in 12345.1 format
		$contributionTracking = explode( '.', $this->order_id );

		$queueMsg = [
			'gateway' => 'dlocal',
			'order_id' => $this->order_id,
			'contribution_tracking_id' => $contributionTracking[0],
			'gateway_txn_id' => $this->id,
			'full_name' => $this->payer['name'],
			'email' => $this->payer['email'],
			'currency' => $this->currency,
			'country' => $this->country,
			'gross' => $this->amount,
			'date' => strtotime( $this->created_date ),
			'gateway_status' => $this->status,
			'dlocal_payment_method' => $this->payment_method_id,
			'payment_method' => $method,
			'payment_submethod' => $submethod
		];

		// If there is a recurring token, add it
		if ( isset( $this->wallet['token'] ) ) {
			$queueMsg['recurring_payment_token'] = $this->wallet['token'];
		}

		// TODO: this needs to be different for refunds and chargebacks
		// Send PAID messages to the jobs queue to get info from the pending table
		$job = [
			'class' => '\SmashPig\PaymentProviders\dlocal\Jobs\PaidMessageJob',
			'payload' => $queueMsg
		];
		return $job;
	}
}
