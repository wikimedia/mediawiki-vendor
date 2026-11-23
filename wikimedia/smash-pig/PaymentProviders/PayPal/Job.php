<?php namespace SmashPig\PaymentProviders\PayPal;

use Exception;
use SmashPig\Core\Context;
use SmashPig\Core\DataStores\QueueWrapper;
use SmashPig\Core\Logging\Logger;
use SmashPig\Core\ProviderConfiguration;
use SmashPig\Core\Runnable;

class Job implements Runnable {

	public $payload;

	/**
	 * @var ProviderConfiguration
	 */
	protected $providerConfiguration;

	public function is_reject() {
		if ( $this->is_gravy() || $this->isBraintree() ) {
			return true;
		}
		// Braintree payments send us a pending auth IPN as well as a completed one
		if ( ( $this->payload['payment_status'] ?? '' ) === 'Pending' ) {
			return true;
		}
		foreach ( $this->providerConfiguration->val( 'rejects' ) as $key => $val ) {
			if ( isset( $this->payload[$key] ) ) {
				$values = (array)$val;
				if ( in_array( $this->payload[$key], $values, true ) ) {
					return true;
				}
			}
		}
		return false;
	}

	/**
	 * True if the IPN names a receiver and none of its receiver fields is in receiver-allowlist.
	 */
	protected function isForeignReceiver(): bool {
		$receivers = array_filter( [
			$this->payload['receiver_id'] ?? null,
			$this->payload['receiver_email'] ?? null,
			$this->payload['business'] ?? null,
		] );
		if ( !$receivers ) {
			return false;
		}
		$allowed = array_map( 'strtolower', (array)$this->providerConfiguration->val( 'receiver-allowlist' ) );
		foreach ( $receivers as $receiver ) {
			if ( in_array( strtolower( $receiver ), $allowed, true ) ) {
				return false;
			}
		}
		return true;
	}

	public function execute() {
		$this->providerConfiguration = Context::get()->getProviderConfiguration();

		if ( $this->is_reject() ) {
			// Returning false would cause it to go to the damaged queue, we
			// just want to forget about these.
			return true;
		}

		if ( $this->isForeignReceiver() ) {
			if ( !$this->providerConfiguration->val( 'receiver-allowlist' ) ) {
				// Keep the IPN in the damaged queue until receiver-allowlist is set.
				Logger::error( 'PayPal receiver-allowlist is empty, cannot check the IPN receiver' );
				return false;
			}
			// PayPal verifies IPNs for every merchant, so a VERIFIED IPN can be for another account.
			Logger::warning( 'Dropping PayPal IPN for a receiver not in receiver-allowlist: ' . json_encode( [
				'txn_id' => $this->payload['txn_id'] ?? null,
				'receiver_id' => $this->payload['receiver_id'] ?? null,
				'receiver_email' => $this->payload['receiver_email'] ?? null,
				'business' => $this->payload['business'] ?? null,
			] ) );
			return true;
		}

		$request = $this->payload;

		// Determine message type.
		if ( isset( $request['txn_type'] ) ) {
			$txn_type = $request['txn_type'];
		} elseif (
			isset( $request['payment_status'] ) &&
			// TODO can these go in config? --------------v-----------v
			in_array( $request['payment_status'], [ 'Reversed', 'Refunded', 'Canceled_Reversal' ] )
		) {
			// refund, chargeback, or reversal
			// Canceled_Reversal means PayPal canceled the reversal and restored funds
			$txn_type = 'refund';
		} else {
			throw new Exception( 'Invalid PayPal message: ' . json_encode( $request ) );
		}

		$msgClass = null;
		$queue = '';
		foreach ( $this->providerConfiguration->val( 'messages' ) as $type => $conf ) {
			if ( in_array( $txn_type, $conf['txn_types'] ) ) {
				$msgClass = $conf['class'];
				$queue = $conf['queue'];
				// When the message has a payment_status specified and the configuration
				// for this message class has a list of valid statuses, return early when
				// the message's status is not in the valid list.
				if (
					isset( $request['payment_status'] ) &&
					isset( $conf['valid_statuses'] ) &&
					!in_array( $request['payment_status'], $conf['valid_statuses'] )
				) {
					return true;
				}
			}
		}

		if ( !$msgClass ) {
			throw new Exception( 'Invalid PayPal message type: ' . $txn_type );
		}

		// Transform into new message.

		$creator = [ $msgClass, 'fromIpnMessage' ];
		$normalized = call_user_func( $creator, $request );

		// Save to appropriate queue.
		QueueWrapper::push( $queue, $normalized );

		// FIXME random document formats
		if ( substr( $txn_type, 0, 7 ) === 'subscr_' ) {
			$log_id = "subscr_id:{$request['subscr_id']}";
		} elseif ( substr( $txn_type, 0, 10 ) === 'recurring_' ) {
			$log_id = "recurring_payment_id:{$request['recurring_payment_id']}";
		} else {
			$log_id = "txn_id:{$request['txn_id']}";
		}

		Logger::info( "Message {$log_id} pushed to {$queue} queue." );

		// TODO It would be nice if push() returned something useful so we
		// could return something here too
		return true;
	}

	/**
	 * Detect Braintree-originated messages by the B- prefix of their 'custom' field
	 * @return bool
	 */
	protected function isBraintree() {
		return isset( $this->payload['custom'] ) &&
			!is_numeric( $this->payload['custom'] ) &&
			str_starts_with( $this->payload['custom'], 'B-' );
	}

	/**
	 * Detect gravy-originated messages by the non-numeric format of their 'custom' field
	 * @return bool
	 */
	protected function is_gravy() {
		return isset( $this->payload['custom'] ) &&
			!is_numeric( $this->payload['custom'] ) &&
			strlen( $this->payload['custom'] ) > 20;
	}
}
