<?php namespace SmashPig\PaymentProviders\Amazon\ExpatriatedMessages;

use SmashPig\Core\Messages\ListenerMessage;

abstract class AmazonMessage extends ListenerMessage {

	protected $gateway_txn_id;
	protected $currency;
	protected $date;
	protected $gross;

	/**
	 * Do common normalizations.  Subclasses should perform normalizations
	 * specific to that message type.
	 *
	 * @return array associative queue message thing
	 */
	public function normalizeForQueue() {
		$queueMsg = [
			'date' => $this->date,
			'gateway' => 'amazon',
			'gross' => $this->gross
		];
		return $queueMsg;
	}

	public function getDestinationQueue() {
		// stub
		return null;
	}

	public function validate(): bool {
		return true;
	}

	public function getCurrency() {
		return $this->currency;
	}

	public function getGross() {
		return $this->gross;
	}

	protected function setGatewayIds( $amazonId ) {
		$this->gateway_txn_id = $amazonId;
	}

	public function getOrderReferenceId() {
		return substr( $this->gateway_txn_id ?? '', 0, 19 );
	}
}
