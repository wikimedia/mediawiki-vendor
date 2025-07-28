<?php

namespace SmashPig\PaymentProviders\Gravy\Actions;

use SmashPig\Core\DataStores\QueueWrapper;
use SmashPig\Core\Logging\TaggedLogger;
use SmashPig\Core\Messages\ListenerMessage;
use SmashPig\PaymentProviders\Gravy\Jobs\RecurringCancellationJob;

class PaymentMethodDeleteAction extends GravyAction {

	public function execute( ListenerMessage $msg ): bool {
		$tl = new TaggedLogger( 'PaymentMethodDeleteAction' );

		// Only process PayPal payment method deletions
		if ( $msg->getPaymentMethod() !== 'paypal' ) {
			$tl->info( "Ignoring non-PayPal payment method deletion: {$msg->getPaymentMethod()}" );
			return true;
		}

		$tl->info( "Processing PayPal payment method deletion for ID: {$msg->getPaymentMethodId()}" );

		// Create job and push to jobs-gravy queue
		$cancellationJob = RecurringCancellationJob::factory( $msg );
		QueueWrapper::push( $msg->getDestinationQueue(), $cancellationJob );

		$tl->info( "Pushed PayPal recurring cancellation job to jobs-gravy queue" );
		return true;
	}
}
