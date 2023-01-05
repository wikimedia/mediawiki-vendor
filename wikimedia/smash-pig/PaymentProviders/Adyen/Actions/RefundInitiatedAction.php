<?php namespace SmashPig\PaymentProviders\Adyen\Actions;

use SmashPig\Core\Actions\IListenerMessageAction;
use SmashPig\Core\Logging\TaggedLogger;
use SmashPig\Core\Messages\ListenerMessage;
use SmashPig\PaymentProviders\Adyen\ExpatriatedMessages\RefundWithData;

/**
 * Action for a refund! whoo!
 */
class RefundInitiatedAction implements IListenerMessageAction {
	public function execute( ListenerMessage $msg ): bool {
		$tl = new TaggedLogger( 'RefundInitiatedAction' );

		if ( $msg instanceof RefundWithData ) {
			// I've never even seen one of these messages so we'll just have to wait
			// and see
			$tl->error(
				"Oh hai! We got a refund on pspReference " .
				"'{$msg->pspReference}'! What do we do now?",
				$msg
			);
		}

		return true;
	}
}
