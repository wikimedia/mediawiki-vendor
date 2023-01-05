<?php namespace SmashPig\PaymentProviders\Adyen\Actions;

use SmashPig\Core\Actions\IListenerMessageAction;
use SmashPig\Core\Logging\TaggedLogger;
use SmashPig\Core\Messages\ListenerMessage;
use SmashPig\PaymentProviders\Adyen\ExpatriatedMessages\ChargebackReversed;

/**
 * Action to fire when an iniated chargeback is canceled.
 */
class ChargebackReversedAction implements IListenerMessageAction {
	public function execute( ListenerMessage $msg ): bool {
		$tl = new TaggedLogger( 'ChargebackInitiatedAction' );

		if ( $msg instanceof ChargebackReversed ) {
			// We get some of these nowadays but haven't yet written code
			// to handle them on the Civi side. Still probably not common
			// enough to make a big difference.
			$tl->warning(
				"Oh hai! We got a chargeback reversal on pspReference " .
				"'{$msg->pspReference}'! What do we do now?",
				$msg
			);
		}

		return true;
	}
}
