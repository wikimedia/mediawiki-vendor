<?php namespace SmashPig\PaymentProviders\Adyen\Actions;

use SmashPig\Core\Actions\IListenerMessageAction;
use SmashPig\Core\DataStores\QueueWrapper;
use SmashPig\Core\Logging\TaggedLogger;
use SmashPig\Core\Messages\ListenerMessage;
use SmashPig\PaymentProviders\Adyen\ExpatriatedMessages\RecurringContract;
use SmashPig\PaymentProviders\Adyen\Jobs\RecurringContractJob;

/**
 * Recurring contracts come in when a new recurring is started. The token for recurring iDEALs
 * comes in on this message
 *
 * @package SmashPig\PaymentProviders\Adyen\Actions
 */
class RecurringContractAction implements IListenerMessageAction {
	public function execute( ListenerMessage $msg ): bool {
		$tl = new TaggedLogger( 'RecurringContractAction' );

		if ( $msg instanceof RecurringContract ) {
			if ( $msg->success ) {
				$tl->info(
					"Adding new recurring with payment method: {$msg->paymentMethod} order ID: " .
					"{$msg->merchantReference} with recurring token: {$msg->pspReference}."
				);
				$recordJob = RecurringContractJob::factory( $msg );
				QueueWrapper::push( 'jobs-adyen', $recordJob );
			} else {
				$tl->warning(
					"Recurring contract failed for payment method: {$msg->paymentMethod} order ID: " .
					"{$msg->merchantReference} with token {$msg->pspReference}.",
					$msg
				);
			}
		}

		return true;
	}
}
