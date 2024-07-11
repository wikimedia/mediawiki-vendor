<?php namespace SmashPig\PaymentProviders\Adyen\Jobs;

use SmashPig\Core\DataStores\PendingDatabase;
use SmashPig\Core\DataStores\QueueWrapper;
use SmashPig\Core\Jobs\RunnableJob;
use SmashPig\Core\Logging\Logger;
use SmashPig\PaymentProviders\Adyen\ExpatriatedMessages\AdyenMessage;

/**
 * Job that merges a recurring contract IPN message from Adyen with donor info from the
 * pending database, then places that into the donations queue.
 *
 * Class RecurringContractJob
 *
 * @package SmashPig\PaymentProviders\Adyen\Jobs
 */
class RecurringContractJob extends RunnableJob {

	protected $gatewayTxnId;
	protected $merchantReference;
	protected $eventDate;
	protected $recurringPaymentToken;
	protected $processorContactId;
	protected $paymentMethod;

	public static function factory( AdyenMessage $ipnMessage ) {
		$obj = new RecurringContractJob();

		$obj->gatewayTxnId = $ipnMessage->getGatewayTxnId();
		$obj->merchantReference = $ipnMessage->merchantReference;
		$obj->eventDate = $ipnMessage->eventDate;
		$obj->recurringPaymentToken = $ipnMessage->pspReference;
		$obj->processorContactId = $ipnMessage->merchantReference;
		$obj->paymentMethod = $ipnMessage->paymentMethod;

		return $obj;
	}

	public function execute() {
		$logger = Logger::getTaggedLogger( "corr_id-adyen-$this->merchantReference" );
		// Find the details from the payment site in the pending database.
		$logger->debug( 'Attempting to locate associated message in pending database' );
		$db = PendingDatabase::get();
		$dbMessage = $db->fetchMessageByGatewayOrderId( 'adyen', $this->merchantReference );

		// We get an RECURRING_CONTRACT for every new recurring but only send it
		// to donations here for recurring SEPA/iDEAL
		if ( $this->paymentMethod == 'ideal' || $this->paymentMethod == 'sepadirectdebit' ) {
			$logger->info(
				"Handling recurring contract IPN for payment method '$this->paymentMethod', order ID " .
				"'$this->merchantReference' and recurring token '$this->recurringPaymentToken'"
			);

			if ( $dbMessage && ( isset( $dbMessage['gateway_txn_id'] ) ) ) {
				$logger->debug(
					'A valid message was obtained from the pending queue. Sending message to donations queue.'
				);

				// Add the recurring setup information
				$dbMessage['recurring_payment_token'] = $this->recurringPaymentToken;
				$dbMessage['processor_contact_id'] = $this->processorContactId;

				QueueWrapper::push( 'donations', $dbMessage );

				// Remove it from the pending database
				$logger->debug( 'Removing donor details message from pending database' );
				$db->deleteMessage( $dbMessage );

			} else {
				// There was no matching pending entry found
				$logger->warning(
					"Could not find donor details for payment method '$this->paymentMethod', order ID: " .
					"'$this->merchantReference', and recurring token: '$this->recurringPaymentToken'",
					$dbMessage
				);
			}
		} else {
			if ( $dbMessage ) {
				// Add the recurring setup information
				$dbMessage['recurring_payment_token'] = $this->recurringPaymentToken;
				$dbMessage['processor_contact_id'] = $this->processorContactId;
				$logger->info(
					"Storing recurring contract IPN info for payment method '$this->paymentMethod', order ID " .
					"'$this->merchantReference' and recurring token '$this->recurringPaymentToken' to matching " .
					'pending db row.'
				);
				$db->storeMessage( $dbMessage );
			} else {
				$logger->info(
					"Discarding recurring contract IPN for payment method '$this->paymentMethod', order ID " .
					"'$this->merchantReference' and recurring token '$this->recurringPaymentToken' with no " .
					'matching pending db row.'
				);
			}
		}

		return true;
	}
}
