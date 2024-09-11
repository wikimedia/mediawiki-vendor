<?php namespace SmashPig\PaymentProviders\Amazon\ExpatriatedMessages;

use SmashPig\Core\UtcDate;

/**
 * Handle Amazon refund notifications
 * https://payments.amazon.com/documentation/apireference/201752720#201752740
 */
abstract class PaymentRefund extends AmazonMessage {

	protected $gateway_parent_id;

	public function __construct( $values ) {
		$details = $values['RefundDetails'];

		$this->setGatewayIds( $details['AmazonRefundId'] );

		$this->date = UtcDate::getUtcTimestamp( $details['CreationTimestamp'] );

		$this->currency = $details['RefundAmount']['CurrencyCode'];
		$this->gross = $details['RefundAmount']['Amount'];
		// TODO: do we need to use FeeRefunded for anything?
	}

	/**
	 * Add fields specific to refund messages
	 *
	 * @return array
	 */
	public function normalizeForQueue() {
		$queueMsg = parent::normalizeForQueue();

		$queueMsg = array_merge( $queueMsg, [
			'gateway_parent_id' => $this->gateway_parent_id,
			'gateway_refund_id' => $this->gateway_txn_id,
			'gross_currency' => $this->currency,
			// Docs say RefundType is always 'SellerInitiated'
			// Waiting to hear back about how they inform us of chargebacks.
			'type' => 'refund',
		] );

		return $queueMsg;
	}

	public function setParentId( $parentId ) {
		$this->gateway_parent_id = $parentId;
	}
}
