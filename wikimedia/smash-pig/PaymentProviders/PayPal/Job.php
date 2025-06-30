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
		if ( $this->is_gravy() ) {
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

	public function execute() {
		$this->providerConfiguration = Context::get()->getProviderConfiguration();

		if ( $this->is_reject() ) {
			// Returning false would cause it to go to the damaged queue, we
			// just want to forget about these.
			return true;
		}

		$request = $this->payload;

		// Determine message type.
		if ( isset( $request['txn_type'] ) ) {
			$txn_type = $request['txn_type'];
		} elseif (
			isset( $request['payment_status'] ) &&
			// TODO can these go in config? --------------v-----------v
			in_array( $request['payment_status'], [ 'Reversed', 'Refunded' ] )
		) {
			// refund, chargeback, or reversal
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
	 * Detect gravy-originated messages by the non-numeric format of their 'custom' field
	 * @return bool
	 */
	protected function is_gravy() {
		return isset( $this->payload['custom'] ) &&
			!is_numeric( $this->payload['custom'] ) &&
			strlen( $this->payload['custom'] ) > 20;
	}
}
