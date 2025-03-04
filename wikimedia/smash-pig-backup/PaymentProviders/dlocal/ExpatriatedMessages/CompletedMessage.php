<?php namespace SmashPig\PaymentProviders\dlocal\ExpatriatedMessages;

/**
 * Message with a status of COMPLETED so far only used for chargebacks
 */
class CompletedMessage extends DlocalMessage {

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
				'gross_currency' => $this->currency,
				'gross' => $this->amount,
				'date' => strtotime( $this->created_date ),
				'gateway' => 'dlocal',
				'type' => 'chargeback',
			];

			return $queueMsg;
	}
}
