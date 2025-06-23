<?php
namespace SmashPig\PaymentProviders\Gravy\Jobs;

use SmashPig\Core\Context;
use SmashPig\Core\DataStores\PendingDatabase;
use SmashPig\Core\DataStores\QueueWrapper;
use SmashPig\Core\Logging\Logger;
use SmashPig\Core\RetryableException;
use SmashPig\Core\Runnable;
use SmashPig\PaymentProviders\Gravy\ExpatriatedMessages\GravyMessage;
use SmashPig\PaymentProviders\Gravy\Factories\GravyGetLatestPaymentStatusResponseFactory;
use SmashPig\PaymentProviders\Responses\PaymentProviderExtendedResponse;

/**
 * Job that sends a Transaction Webhook message from Gravy into the donations queue.
 *
 * Class TransactionMessageJob
 *
 * @package SmashPig\PaymentProviders\Gravy\Jobs
 */
class RecordCaptureJob implements Runnable {

	public array $payload;

	public static function factory( GravyMessage $message, PaymentProviderExtendedResponse $transactionDetails ): array {
		return [
			'class' => self::class,
			'payload' => array_merge(
				[
					'eventDate' => $message->getMessageDate()
				], $transactionDetails->getNormalizedResponse()
			)
		];
	}

	public function execute(): bool {
		/** @var PaymentProviderExtendedResponse $transactionDetails */
		$transactionDetails = GravyGetLatestPaymentStatusResponseFactory::fromNormalizedResponse( $this->payload );
		$logger = Logger::getTaggedLogger( "corr_id-gravy-{$transactionDetails->getOrderId()}" );
		$logger->info(
			'Recording successful capture of Gravy transaction with authorization reference ' .
				"'{$transactionDetails->getGatewayTxnId()}' and order ID '{$transactionDetails->getOrderId()}'."
		);

		// Find the details from the payment site in the pending database.
		$logger->debug( 'Attempting to locate associated message in pending database' );

		$db = PendingDatabase::get();
		$dbMessage = $db->fetchMessageByGatewayOrderId( 'gravy', $transactionDetails->getOrderId() );

		if ( $dbMessage && ( isset( $dbMessage['order_id'] ) ) ) {
			$logger->debug( 'A valid message was obtained from the pending queue' );

			$this->addMissingFieldsToPendingRecord( $dbMessage, $transactionDetails );

			// Use the eventDate from the capture as the date
			$dbMessage['date'] = strtotime( $this->payload['eventDate'] );

			QueueWrapper::push( 'donations', $dbMessage );

			// Remove it from the pending database
			$logger->debug( 'Removing donor details message from pending database' );
			$db->deleteMessage( $dbMessage );

		} else {
			$logger->warning(
				"Could not find donor details for authorization Reference '{$transactionDetails->getGatewayTxnId()}' " .
					"and order ID '{$transactionDetails->getOrderId()}'.",
				$dbMessage
			);
		}

		return true;
	}

	protected function addMissingFieldsToPendingRecord( array &$dbMessage, PaymentProviderExtendedResponse $partialTransactionDetails ): void {
		// Add the gateway transaction ID
		$dbMessage['gateway_txn_id'] = $partialTransactionDetails->getGatewayTxnId();

		$transactionDetails = $this->getFullTransactionDetails( $partialTransactionDetails->getGatewayTxnId() );

		// Other things that are missing for e.g. 3d-secure transactions
		if ( empty( $dbMessage['backend_processor'] ) ) {
			$dbMessage['backend_processor'] = $transactionDetails->getBackendProcessor();
		}
		if ( empty( $dbMessage['backend_processor_txn_id'] ) ) {
			$dbMessage['backend_processor_txn_id'] = $transactionDetails->getBackendProcessorTransactionId();
		}
		if ( empty( $dbMessage['payment_submethod'] ) ) {
			$dbMessage['payment_submethod'] = $transactionDetails->getPaymentSubmethod();
		}
		if ( empty( $dbMessage['payment_orchestrator_reconciliation_id'] ) ) {
			$dbMessage['payment_orchestrator_reconciliation_id'] = $transactionDetails->getPaymentOrchestratorReconciliationId();
		}
		// Special handling for recurring donations
		if ( !empty( $dbMessage['recurring'] ) ) {
			if ( empty( $dbMessage['recurring_payment_token'] ) ) {
				if ( !empty( $transactionDetails->getRecurringPaymentToken() ) ) {
					$dbMessage['recurring_payment_token'] = $transactionDetails->getRecurringPaymentToken();
				} else {
					throw new RetryableException(
						'Recurring message was obtained from the pending queue with no token. Requeuing job.'
					);
				}
			}
		}
	}

	public function getFullTransactionDetails( string $gateway_txn_id ): PaymentProviderExtendedResponse {
		$providerConfiguration = Context::get()->getProviderConfiguration();
		$provider = $providerConfiguration->object( 'payment-provider/cc' );

		$transactionDetails = $provider->getLatestPaymentStatus( [
			'gateway_txn_id' => $gateway_txn_id,
		] );

		return $transactionDetails;
	}
}
