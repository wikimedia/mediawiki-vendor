<?php

namespace SmashPig\PaymentProviders\Gravy\Actions;

use RuntimeException;
use SmashPig\Core\Context;
use SmashPig\Core\DataStores\QueueWrapper;
use SmashPig\Core\Helpers\CurrencyRoundingHelper;
use SmashPig\Core\Logging\TaggedLogger;
use SmashPig\Core\Messages\ListenerMessage;
use SmashPig\PaymentData\FinalStatus;
use SmashPig\PaymentProviders\Gravy\Errors\ErrorMapper;
use SmashPig\PaymentProviders\Gravy\ExpatriatedMessages\TransactionMessage;
use SmashPig\PaymentProviders\Gravy\Jobs\ProcessCaptureRequestJob;
use SmashPig\PaymentProviders\Gravy\Jobs\RecordCaptureJob;
use SmashPig\PaymentProviders\Gravy\Jobs\RecordFailureJob;
use SmashPig\PaymentProviders\Gravy\TransactionDetailsNormalizer;
use SmashPig\PaymentProviders\Responses\PaymentProviderExtendedResponse;

class TransactionAction extends GravyAction {
	use RefundTrait;

	public function execute( ListenerMessage $msg ): bool {
		if ( !$msg instanceof TransactionMessage ) {
			throw new RuntimeException( 'Needs a TransactionMessage' );
		}
		$tl = new TaggedLogger( 'TransactionAction' );
		$transactionDetails = $this->getTransactionDetails( $msg );

		if ( $transactionDetails->isSuccessful() ) {
			if ( $transactionDetails->getStatus() == FinalStatus::COMPLETE ) {
				$tl->info(
					"Adding successful capture job for {$transactionDetails->getCurrency()} {$transactionDetails->getAmount()} with psp reference {$transactionDetails->getGatewayTxnId()}."
				);

				$recordCaptureJob = RecordCaptureJob::factory( $msg, $transactionDetails );
				QueueWrapper::push( $msg->getDestinationQueue(), $recordCaptureJob );
			} elseif ( $transactionDetails->getStatus() == FinalStatus::PENDING_POKE ) {
				$providerConfig = Context::get()->getProviderConfiguration();
				if ( !$providerConfig->val( 'capture-from-ipn-listener' ) ) {
					return true;
				}
				$tl->info(
					"Adding successful authorized job for {$transactionDetails->getCurrency()} {$transactionDetails->getAmount()} with psp reference {$transactionDetails->getGatewayTxnId()}"
				);
				$captureRequestJob = ProcessCaptureRequestJob::factory( $msg, $transactionDetails );
				QueueWrapper::push( $msg->getDestinationQueue(), $captureRequestJob );
			} else {
				$tl->info(
					"Received successful transaction with unknown status {$transactionDetails->getStatus()} and transaction id {$transactionDetails->getGatewayTxnId()}"
				);
			}
		} else {
			$id = $transactionDetails->getRawResponse()['id'] ?? null;
			$message = 'Skipping unsuccessful transaction';
			if ( !empty( $id ) ) {
				if ( $this->requiresCancel( $transactionDetails ) ) {
					$message = "Pushing failed transaction with id: {$id} as a cancellation to donations queue.";
					$this->pushFailedAuthToJobsQueue( $msg );
				} else {
					$message = "Skipping unsuccessful transaction with transaction id {$id}";
				}
			}
			$tl->info( $message );
		}

		return true;
	}

	public function getTransactionDetails( TransactionMessage $msg ): PaymentProviderExtendedResponse {
		$transactionDetailsNormalizer = new TransactionDetailsNormalizer();
		$paymentMethod = $msg->getTransactionPaymentMethod();
		$transactionDetails = $msg->getTransactionDetails();

		// FIXME: this doesn't seem to create a fully useful PaymentProviderResponse object
		// For example, it has gateway_txn_id in the normalizedResponse property,
		// but it returns null from getGatewayTxnId()
		return $transactionDetailsNormalizer->normalizeTransactionDetails(
			$paymentMethod,
			$transactionDetails
		);
	}

	/**
	 * Some payment method requires a cancellation message when it fails
	 * because they are set to complete status before getting a successful response
	 * @param PaymentProviderExtendedResponse $transaction
	 * @return bool
	 */
	public function requiresCancel( PaymentProviderExtendedResponse $transaction ): bool {
		$normalizedResponse = $transaction->getNormalizedResponse();
		return isset( $normalizedResponse['type'] ) && $normalizedResponse['type'] == "chargeback";
	}

	/**
	 * Send donation modification message to Civi. Needs to go through the
	 * jobs-gravy queue first to make sure it arrives after the original
	 * donation.
	 * @param TransactionMessage $msg
	 * @return void
	 */
	public function pushFailedAuthToJobsQueue( TransactionMessage $msg ): void {
		$details = $msg->getTransactionDetails();
		$reason = $details['error_code'] ?? '';
		$failureMessage = [
			'contribution_status_id:name' => 'Cancelled',
			'gateway_txn_id' => $details['id'],
			'payment_method' => 'ach',
			'order_id' => $details['external_identifier'],
			'gross_currency' => $details['currency'],
			'gross' => CurrencyRoundingHelper::getAmountInMajorUnits(
				$details['amount'], $details['currency']
			),
			'backend_processor' => 'trustly',
			'backend_processor_txn_id' => $details['payment_service_transaction_id'],
			'date' => strtotime( $msg->getMessageDate() ),
			'gateway' => 'gravy',
			'reason' => $reason,
			'can_retry' => $this->isRetryableErrorCode( $reason ),
			'is_suspected_fraud' => ErrorMapper::isSuspectedFraud( $reason )
		];
		$job = RecordFailureJob::factory( $failureMessage );
		QueueWrapper::push( 'jobs-gravy', $job );
	}

	protected function isRetryableErrorCode( string $errorCode ): bool {
		// Seen so far:
		// * canceled_payment_method
		// * insufficient_funds
		// * suspected_fraud
		return $errorCode === 'insufficient_funds';
	}
}
