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
		'card',
		'event_info',
		'amount',
		'status',
		'status_detail',
		'status_code',
		'currency',
		'country',
		'payment_id',
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
	 * On refunds, this is the id of the original payment
	 * @var string
	 */
	protected $payment_id;

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
	 * @var mixed
	 */
	protected $status_detail;

	/**
	 * @var array
	 */
	protected $wallet;

	/**
	 * @var array
	 */
	protected $card;

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
