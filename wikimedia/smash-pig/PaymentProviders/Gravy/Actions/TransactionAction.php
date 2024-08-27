<?php

namespace SmashPig\PaymentProviders\Gravy\Actions;

use SmashPig\Core\Context;
use SmashPig\Core\DataStores\QueueWrapper;
use SmashPig\Core\Logging\TaggedLogger;
use SmashPig\Core\Messages\ListenerMessage;
use SmashPig\PaymentData\FinalStatus;
use SmashPig\PaymentProviders\Gravy\ExpatriatedMessages\TransactionMessage;
use SmashPig\PaymentProviders\Gravy\Jobs\ProcessCaptureRequestJob;
use SmashPig\PaymentProviders\Gravy\Jobs\RecordCaptureJob;
use SmashPig\PaymentProviders\Responses\PaymentDetailResponse;

class TransactionAction extends GravyAction {
	 public function execute( ListenerMessage $msg ): bool {
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
			$message = "Skipping unsuccessful transaction";
			if ( !empty( $id ) ) {
				$message = "Skipping unsuccessful transaction with transaction id {$id}";
			}
			$tl->info( $message );
		}

		return true;
	 }

	public function getTransactionDetails( TransactionMessage $msg ): PaymentDetailResponse {
		$providerConfiguration = Context::get()->getProviderConfiguration();
		$provider = $providerConfiguration->object( 'payment-provider/cc' );

		$transactionDetails = $provider->getPaymentDetails( [
			"gateway_txn_id" => $msg->getTransactionId()
		] );

		return $transactionDetails;
	}
}
