<?php

namespace SmashPig\PaymentProviders\Gravy\Jobs;

use SmashPig\Core\DataStores\QueueWrapper;
use SmashPig\Core\Logging\Logger;
use SmashPig\Core\Runnable;
use SmashPig\PaymentProviders\Gravy\ExpatriatedMessages\PaymentMethodMessage;

class RecurringCancellationJob implements Runnable {

	public array $payload;

	public static function factory( PaymentMethodMessage $message ): array {
		return [
			'class' => self::class,
			'payload' => [
				'gateway' => 'gravy',
				'txn_type' => 'subscr_cancel',
				'subscr_id' => $message->getPaymentMethodId(), // PayPal Job expects this field
				'payment_method' => $message->getPaymentMethod(),
				'date' => strtotime( $message->getMessageDate() ),
				'cancel_date' => strtotime( $message->getPaymentMethodUpdateDate() ),
				'recurring' => '1',
			]
		];
	}

	public function execute(): bool {
		$logger = Logger::getTaggedLogger( 'RecurringCancellationJob' );

		if ( empty( $this->payload['subscr_id'] ) ) {
			$logger->error( 'Missing subscription ID in cancellation job' );
			return false;
		}

		$logger->info(
			"Processing recurring cancellation for payment method: {$this->payload['subscr_id']}"
		);

		// Now push the normalized message to the recurring queue
		$recurringMessage = $this->payload;
		QueueWrapper::push( 'recurring', $recurringMessage );
		$logger->info( "Pushed recurring cancellation message to recurring queue" );
		return true;
	}
}
