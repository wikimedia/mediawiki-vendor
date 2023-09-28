<?php namespace SmashPig\PaymentProviders\dlocal\ExpatriatedMessages;

use SmashPig\PaymentProviders\dlocal\Jobs\RejectedMessageJob;
use SmashPig\PaymentProviders\dlocal\ReferenceData;

/**
 * Message model for REJECTED IPN messages
 */
class RejectedMessage extends DlocalMessage {

	public $status_code;

	public function getDestinationQueue() {
		return 'jobs-dlocal';
	}

	/**
	 * @return array
	 */
	public function normalizeForQueue() : array {
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
			'gateway_status_code' => $this->status_code,
			'gateway_status_detail' => $this->status_detail,
			'dlocal_payment_method' => $this->payment_method_id,
			'payment_method' => $method,
			'payment_submethod' => $submethod
		];

		// If there is a recurring token, add it
		if ( isset( $this->wallet['token'] ) ) {
			$queueMsg['recurring_payment_token'] = $this->wallet['token'];
		}

		return [
			'class' => RejectedMessageJob::class,
			'payload' => $queueMsg
		];
	}
}
