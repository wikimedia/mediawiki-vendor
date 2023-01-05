<?php namespace SmashPig\PaymentProviders\AstroPay\ExpatriatedMessages;

use SmashPig\Core\Messages\ListenerMessage;

abstract class AstroPayMessage extends ListenerMessage {

	protected $fields = [
		'result',
		'x_invoice',
		'x_iduser',
		'x_description',
		'x_document',
		'x_bank',
		'x_payment_type',
		'x_bank_name',
		'x_amount',
		'x_control',
		'x_currency',
	];

	protected $result;
	protected $x_invoice;
	protected $x_iduser;
	protected $x_description;
	protected $x_document;
	protected $x_bank;
	protected $x_payment_type;
	protected $x_bank_name;
	protected $x_amount;
	protected $x_control;
	protected $x_currency;

	public function validate(): bool {
		return true;
	}

	public function getSignedString() {
		return $this->result . $this->x_amount . $this->x_invoice;
	}

	public function getSignature() {
		return $this->x_control;
	}

	public function constructFromValues( array $values ) {
		foreach ( $this->fields as $key ) {
			$this->$key = ( array_key_exists( $key, $values ) ? $values[$key] : '' );
		}
	}

	abstract public function getDestinationQueue();

	/**
	 * Map AstroPay's fields to ours
	 *
	 * @return array associative queue message thing
	 */
	public function normalizeForQueue() {
		// AstroPay invoice format is ct_id.numAttempt
		$invoiceParts = explode( '.', $this->x_invoice );

		$queueMsg = [
			'gateway' => 'astropay',
			'contribution_tracking_id' => $invoiceParts[0],
			'gateway_txn_id' => $this->x_document,
			'currency' => $this->x_currency,
			'gross' => $this->x_amount,
			'date' => time(),
			'gateway_status' => $this->result,
			// This message has no donor info.  Add a key to indicate that there is
			// a message in the pending database with the rest of the info we need.
			// We don't get the gateway transaction ID unless the donor makes it
			// back to the thank you page.
			// TODO: see comment on Amazon\ExpatriatedMessages\PaymentCapture->completion_message_id
			'completion_message_id' => 'astropay-' . $this->x_invoice,
			'order_id' => $this->x_invoice
		];

		return $queueMsg;
	}
}
