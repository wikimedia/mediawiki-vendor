<?php namespace SmashPig\PaymentProviders\Amazon\ExpatriatedMessages;

use SmashPig\Core\UtcDate;

abstract class PaymentAuthorization extends AmazonMessage {

	protected $order_id;
	protected $contribution_tracking_id;
	protected $amount;

	public function __construct( $values ) {
		$details = $values['AuthorizationDetails'];

		$captureReferenceId = $details['AuthorizationReferenceId'];

		$this->setOrderId( $captureReferenceId );

		$this->date = UtcDate::getUtcTimestamp( $details['CreationTimestamp'] );

		$this->currency = $details['AuthorizationAmount']['CurrencyCode'];
		$this->gross = $details['AuthorizationAmount']['Amount'];
	}

	/**
	 * Set fields derived from the order ID
	 *
	 * @param string $orderId
	 */
	public function setOrderId( $orderId ) {
		$this->order_id = $orderId;

		$parts = explode( '-', $orderId );
		$this->contribution_tracking_id = $parts[0];
	}

	/**
	 * @return string
	 */
	public function getOrderId() {
		return $this->order_id;
	}
}
