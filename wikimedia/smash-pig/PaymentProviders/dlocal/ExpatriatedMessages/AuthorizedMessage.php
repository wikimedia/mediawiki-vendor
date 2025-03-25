<?php namespace SmashPig\PaymentProviders\dlocal\ExpatriatedMessages;

/**
 * Message indicating a successful authorization
 */
class AuthorizedMessage extends DlocalMessage {
	public function getDestinationQueue() {
		return 'jobs-dlocal';
	}

	/**
	 * Map dlocal's fields to ours
	 *
	 * @return array $queueMsg
	 */
	public function normalizeForQueue() {
		// Normalize the payment method and submethod
		[ $method, $submethod ] = $this->decodePaymentMethod();

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
			'payment_submethod' => $submethod,
		];

		$token = $this->wallet['token'] ?? $this->card['token'] ?? null;
		// If there is a recurring token, add it
		if ( $token ) {
			$queueMsg['recurring_payment_token'] = $token;
		}

		// Send AUTHORIZED messages to the jobs queue to send info from the pending table
		$job = [
			'class' => '\SmashPig\PaymentProviders\dlocal\Jobs\AuthorizedMessageJob',
			'payload' => $queueMsg
		];
		return $job;
	}
}
