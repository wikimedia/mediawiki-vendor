<?php namespace SmashPig\PaymentProviders\Amazon\Actions;

use SmashPig\Core\Actions\IListenerMessageAction;
use SmashPig\Core\DataStores\QueueWrapper;
use SmashPig\Core\Logging\Logger;
use SmashPig\Core\Messages\ListenerMessage;

class AddMessageToQueue implements IListenerMessageAction {
	public function execute( ListenerMessage $msg ): bool {
		// FIXME: I don't like this dispatch style
		$destinationQueue = $msg->getDestinationQueue();

		if ( $destinationQueue ) {
			$queueMsg = $msg->normalizeForQueue();
			QueueWrapper::push( $destinationQueue, $queueMsg );
		} else {
			$class = get_class( $msg );
			Logger::warning( "Ignoring message of type {$class}", $msg );
		}

		return true;
	}
}
