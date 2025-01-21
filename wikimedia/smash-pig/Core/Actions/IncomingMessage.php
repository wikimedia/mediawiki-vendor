<?php namespace SmashPig\Core\Actions;

use SmashPig\Core\DataStores\QueueWrapper;
use SmashPig\Core\Logging\Logger;
use SmashPig\Core\Messages\ListenerMessage;

class IncomingMessage implements IListenerMessageAction {
	public function execute( ListenerMessage $msg ): bool {
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
