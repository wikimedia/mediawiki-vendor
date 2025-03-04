<?php namespace SmashPig\PaymentProviders\dlocal\ExpatriatedMessages;

use SmashPig\Core\Messages\ListenerMessage;
use SmashPig\PaymentProviders\dlocal\ReferenceData;

abstract class DlocalMessage extends ListenerMessage {

	/**
	 * @var array
	 */
	protected $fields = [
		'amount',
		'authorization',
		'callback_url',
		'card',
		'country',
		'created_date',
		'currency',
		'description',
		'document',
		'email',
		'event_info',
		'id',
		'notification_url',
		'order_id',
		'payer',
		'payment_id',
		'payment_method_flow',
		'payment_method_id',
		'payment_method_type',
		'signatureInput',
		'status',
		'status_code',
		'status_detail',
		'type',
		'user_reference',
		'wallet'
	];

	/**
	 * @var mixed
	 */
	protected $amount;

	/**
	 * @var mixed
	 */
	public $authorization;

	/**
	 * @var string
	 */
	public $callback_url;

	/**
	 * @var array
	 */
	protected $card;

	/**
	 * @var mixed
	 */
	protected $country;

	/**
	 * @var mixed
	 */
	protected $created_date;

	/**
	 * @var mixed
	 */
	protected $currency;

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
	protected $email;

	/**
	 * @var mixed
	 */
	protected $event_info;

	/**
	 * @var mixed
	 */
	protected $id;

	/**
	 * @var string
	 */
	protected $notification_url;

	/**
	 * @var mixed
	 */
	protected $order_id;

	/**
	 * @var array
	 */
	protected $payer;

	/**
	 * On refunds, this is the id of the original payment
	 * @var string
	 */
	protected $payment_id;

	/**
	 * @var mixed
	 */
	protected $payment_method_flow;

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
	public $signatureInput;

	/**
	 * @var mixed
	 */
	protected $status;

	/**
	 * @var mixed
	 */
	protected $status_code;

	/**
	 * @var mixed
	 */
	protected $status_detail;

	/**
	 * @var mixed
	 */
	protected $type;

	/**
	 * @var mixed
	 */
	protected $user_reference;

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

	abstract public function normalizeForQueue();

	protected function decodePaymentMethod(): array {
		if ( is_array( $this->card ) && isset( $this->card['brand'] ) ) {
			return ReferenceData::decodePaymentMethod(
				$this->payment_method_type,
				$this->card['brand']
			);
		}
		return ReferenceData::decodePaymentMethod(
			$this->payment_method_type,
			$this->payment_method_id
		);
	}
}
