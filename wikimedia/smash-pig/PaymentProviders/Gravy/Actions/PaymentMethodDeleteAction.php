<?php

namespace SmashPig\PaymentProviders\Gravy\Actions;

use SmashPig\Core\DataStores\QueueWrapper;
use SmashPig\Core\Logging\TaggedLogger;
use SmashPig\Core\Messages\ListenerMessage;
use SmashPig\PaymentProviders\Gravy\Jobs\RecurringCancellationJob;

class PaymentMethodDeleteAction extends GravyAction {

	public function execute( ListenerMessage $msg ): bool {
		$tl = new TaggedLogger( 'PaymentMethodDeleteAction' );

		// Only process below payment methods deletions
		$methodsNeedDeletion = [ 'paypal', 'pix' ];
		$paymentMethod = $msg->getPaymentMethod();
		if ( in_array( $paymentMethod, $methodsNeedDeletion ) === false ) {
			$tl->info( "Ignoring non-PayPal/Pix payment method deletion: $paymentMethod" );
			return true;
		}

		$tl->info( "Processing payment method deletion for $paymentMethod with ID: {$msg->getPaymentMethodId()}" );

		// Create job and push to jobs-gravy queue
		$cancellationJob = RecurringCancellationJob::factory( $msg );
		QueueWrapper::push( $msg->getDestinationQueue(), $cancellationJob );

		$tl->info( "Pushed $paymentMethod recurring cancellation job to jobs-gravy queue" );
		return true;
	}
}
