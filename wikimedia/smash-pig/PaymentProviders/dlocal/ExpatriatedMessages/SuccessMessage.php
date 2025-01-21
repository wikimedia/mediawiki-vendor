<?php namespace SmashPig\PaymentProviders\dlocal\ExpatriatedMessages;

/**
 * Message with a status of SUCCESS so far only used for refunds
 */
class SuccessMessage extends DlocalMessage {

	public function getDestinationQueue() {
		return 'refund';
	}

	/**
	 * Map dlocal's fields to ours
	 *
	 * @return array $queueMsg
	 */
	public function normalizeForQueue() {
			$queueMsg = [
				'gateway_parent_id' => $this->payment_id,
				'gateway_refund_id' => $this->id,
				'gross_currency' => $this->currency,
				'gross' => $this->amount,
				'date' => strtotime( $this->created_date ),
				'gateway' => 'dlocal',
				'type' => 'refund',
			];

			return $queueMsg;
	}
}
