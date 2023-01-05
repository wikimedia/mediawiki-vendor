<?php namespace SmashPig\PaymentProviders\Adyen\Actions;

use SmashPig\Core\Actions\IListenerMessageAction;
use SmashPig\Core\Context;
use SmashPig\Core\DataStores\QueueWrapper;
use SmashPig\Core\Logging\TaggedLogger;
use SmashPig\Core\Messages\ListenerMessage;
use SmashPig\PaymentProviders\Adyen\ExpatriatedMessages\Authorisation;
use SmashPig\PaymentProviders\Adyen\Jobs\ProcessCaptureRequestJob;
use SmashPig\PaymentProviders\Adyen\Jobs\RecordCaptureJob;

/**
 * When an authorization message from Adyen comes in, we need to either place
 * a capture request into the job queue, or we need to slay its orphan because
 * the transaction failed.
 */
class PaymentCaptureAction implements IListenerMessageAction {
	public function execute( ListenerMessage $msg ): bool {
		$tl = new TaggedLogger( 'PaymentCaptureAction' );

		if ( $msg instanceof Authorisation ) {
			if ( $msg->success ) {
				// Ignore subsequent recurring IPNs
				if ( $msg->isRecurringInstallment() ) {
					return true;
				}
				// For iDEAL, treat this as the final notification of success. We don't
				// need to make any more API calls, just record it in Civi.
				if ( isset( $msg->paymentMethod ) && $msg->paymentMethod == 'ideal' ) {
					$tl->info(
						"Adding Adyen record capture job for {$msg->currency} {$msg->amount} " .
						"with psp reference {$msg->pspReference}."
					);
					$job = RecordCaptureJob::factory( $msg );
					QueueWrapper::push( 'jobs-adyen', $job );
				} else {
					$providerConfig = Context::get()->getProviderConfiguration();
					if ( !$providerConfig->val( 'capture-from-ipn-listener' ) ) {
						return true;
					}
					// Here we need to capture the payment, the job runner will collect the
					// orphan message
					$tl->info(
						"Adding Adyen capture job for {$msg->currency} {$msg->amount} " .
						"with psp reference {$msg->pspReference}."
					);
					$job = ProcessCaptureRequestJob::factory( $msg );
					$queueName = 'jobs-adyen';
					$jobQueueCount = $providerConfig->val(
						'capture-job-queue-count'
					);
					if ( $jobQueueCount > 1 ) {
						$queueNum = rand( 1, $jobQueueCount );
						$queueName .= "-$queueNum";
					}
					QueueWrapper::push( $queueName, $job );
				}
			} else {
				// Here we could decide to delete the data from the pending
				// table, but we don't because donors can potentially re-use
				// a merchant reference by reloading an Adyen hosted page.
				$tl->info(
					"Adyen payment with psp reference {$msg->pspReference} " .
					"reported status failed: '{$msg->reason}'."
				);
			}
		}

		return true;
	}
}
