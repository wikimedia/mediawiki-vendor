<?php

namespace SmashPig\PaymentProviders\Adyen\Actions;

use SmashPig\Core\Actions\IListenerMessageAction;
use SmashPig\Core\DataStores\QueueWrapper;
use SmashPig\Core\Messages\ListenerMessage;
use SmashPig\PaymentProviders\Adyen\ExpatriatedMessages\Authorisation;

class CancelRecurringAction implements IListenerMessageAction {

	public function execute( ListenerMessage $msg ): bool {
		/** @var Authorisation $msg */
		QueueWrapper::push( 'recurring', [
			'txn_type' => 'subscr_cancel',
			'rescue_reference' => $msg->retryRescueReference,
			'is_autorescue' => true,
			'cancel_reason' => 'Payment cannot be rescued: maximum failures reached'
		] );
		return true;
	}
}
