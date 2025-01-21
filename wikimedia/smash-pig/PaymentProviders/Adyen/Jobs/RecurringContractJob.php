<?php namespace SmashPig\PaymentProviders\Adyen\Jobs;

use SmashPig\Core\DataStores\PendingDatabase;
use SmashPig\Core\DataStores\QueueWrapper;
use SmashPig\Core\Logging\Logger;
use SmashPig\Core\Runnable;
use SmashPig\PaymentProviders\Adyen\ExpatriatedMessages\AdyenMessage;

/**
 * Job that merges a recurring contract IPN message from Adyen with donor info from the
 * pending database, then places that into the donations queue.
 *
 * Class RecurringContractJob
 *
 * @package SmashPig\PaymentProviders\Adyen\Jobs
 */
class RecurringContractJob implements Runnable {

	public array $payload;

	public static function factory( AdyenMessage $ipnMessage ): array {
		return [
			'class' => self::class,
			'payload' => [
				'gatewayTxnId' => $ipnMessage->getGatewayTxnId(),
				'merchantReference' => $ipnMessage->merchantReference,
				'eventDate' => $ipnMessage->eventDate,
				'recurringPaymentToken' => $ipnMessage->pspReference,
				'processorContactId' => $ipnMessage->merchantReference,
				'paymentMethod' => $ipnMessage->paymentMethod,
			],
		];
	}

	public function execute() {
		$logger = Logger::getTaggedLogger( "corr_id-adyen-{$this->payload['merchantReference']}" );
		// Find the details from the payment site in the pending database.
		$logger->debug( 'Attempting to locate associated message in pending database' );
		$db = PendingDatabase::get();
		$dbMessage = $db->fetchMessageByGatewayOrderId( 'adyen', $this->payload['merchantReference'] );

		// We get an RECURRING_CONTRACT for every new recurring but only send it
		// to donations here for recurring SEPA/iDEAL
		if ( $this->payload['paymentMethod'] == 'ideal' || $this->payload['paymentMethod'] == 'sepadirectdebit' ) {
			$logger->info(
				"Handling recurring contract IPN for payment method '{$this->payload['paymentMethod']}', order ID " .
				"'{$this->payload['merchantReference']}' and recurring token '{$this->payload['recurringPaymentToken']}'"
			);

			if ( $dbMessage ) {
				$logger->debug(
					'A valid message was obtained from the pending queue. Sending message to donations queue.'
				);

				// Add the recurring setup information
				$dbMessage['recurring_payment_token'] = $this->payload['recurringPaymentToken'];
				$dbMessage['processor_contact_id'] = $this->payload['processorContactId'];
				$dbMessage['gateway_txn_id'] = $this->payload['gatewayTxnId'];

				QueueWrapper::push( 'donations', $dbMessage );

				// Remove it from the pending database
				$logger->debug( 'Removing donor details message from pending database' );
				$db->deleteMessage( $dbMessage );

			} else {
				// There was no matching pending entry found
				$logger->warning(
					"Could not find donor details for payment method '{$this->payload['paymentMethod']}', order ID: " .
					"'{$this->payload['merchantReference']}', and recurring token: '{$this->payload['recurringPaymentToken']}'",
					$dbMessage
				);
			}
		} else {
			if ( $dbMessage ) {
				// Add the recurring setup information
				$dbMessage['recurring_payment_token'] = $this->payload['recurringPaymentToken'];
				$dbMessage['processor_contact_id'] = $this->payload['processorContactId'];
				$logger->info(
					"Storing recurring contract IPN info for payment method '{$this->payload['paymentMethod']}', order ID " .
					"'{$this->payload['merchantReference']}' and recurring token '{$this->payload['recurringPaymentToken']}' to matching " .
					'pending db row.'
				);
				$db->storeMessage( $dbMessage );
			} else {
				$logger->info(
					"Discarding recurring contract IPN for payment method '{$this->payload['paymentMethod']}', order ID " .
					"'{$this->payload['merchantReference']}' and recurring token '{$this->payload['recurringPaymentToken']}' with no " .
					'matching pending db row.'
				);
			}
		}

		return true;
	}
}
