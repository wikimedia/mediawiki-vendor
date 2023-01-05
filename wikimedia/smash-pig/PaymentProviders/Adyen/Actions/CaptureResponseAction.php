<?php namespace SmashPig\PaymentProviders\Adyen\Actions;

use SmashPig\Core\Actions\IListenerMessageAction;
use SmashPig\Core\DataStores\QueueWrapper;
use SmashPig\Core\Logging\TaggedLogger;
use SmashPig\Core\Messages\ListenerMessage;
use SmashPig\PaymentProviders\Adyen\ExpatriatedMessages\Capture;
use SmashPig\PaymentProviders\Adyen\Jobs\RecordCaptureJob;

/**
 * Action that takes place after a Capture modification request has completed.
 *
 * @package SmashPig\PaymentProviders\Adyen\Actions
 */
class CaptureResponseAction implements IListenerMessageAction {
	public function execute( ListenerMessage $msg ): bool {
		$tl = new TaggedLogger( 'CaptureResponseAction' );

		if ( $msg instanceof Capture ) {
			if ( $msg->success ) {
				$tl->info(
					"Adding record capture job for {$msg->currency} {$msg->amount} with psp reference {$msg->pspReference}."
				);
				$recordJob = RecordCaptureJob::factory( $msg );
				QueueWrapper::push( 'jobs-adyen', $recordJob );
			} else {
				$tl->warning(
					"Capture failed for payment with reference {$msg->pspReference}.",
					$msg
				);
			}
		}

		return true;
	}
}
