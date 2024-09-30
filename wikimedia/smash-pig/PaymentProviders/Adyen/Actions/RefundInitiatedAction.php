<?php namespace SmashPig\PaymentProviders\Adyen\Actions;

use SmashPig\Core\Actions\IListenerMessageAction;
use SmashPig\Core\DataStores\QueueWrapper;
use SmashPig\Core\Logging\TaggedLogger;
use SmashPig\Core\Messages\ListenerMessage;
use SmashPig\PaymentProviders\Adyen\ExpatriatedMessages\Refund;
use SmashPig\PaymentProviders\Adyen\ExpatriatedMessages\RefundWithData;

/**
 * Action for a refund! whoo!
 */
class RefundInitiatedAction extends BaseRefundAction implements IListenerMessageAction {
	use DropGravyInitiatedMessageTrait;

	public function execute( ListenerMessage $msg ): bool {
		$tl = new TaggedLogger( 'RefundInitiatedAction' );

		if ( $msg instanceof Refund ) {
			if ( $msg->success ) {
				// drop Gr4vy initiated message
				if ( $this->isGravyInitiatedMessage( $msg, 'refund' ) ) {
					return true;
				}
				$tl->info(
					"Adding refund for {$msg->currency} {$msg->amount} with psp reference {$msg->pspReference} and originalReference {$msg->parentPspReference}."
				);
				$queueMessage = $this->normalizeMessageForQueue( $msg );
				QueueWrapper::push( 'refund', $queueMessage );
			} else {
				$tl->info(
					"Got a failed refund for {$msg->currency} {$msg->amount} with psp reference {$msg->pspReference} and originalReference {$msg->parentPspReference}. Doing nothing."
				);
			}
			if ( $msg instanceof RefundWithData ) {
				// I've never even seen one of these messages, so we'll just have to wait and see
				$tl->error(
					"Oh hai! We got a refund with data on pspReference " .
					"'{$msg->pspReference}'! What do we do now?",
					$msg
				);
			}
		}

		return true;
	}

	protected function getTypeForQueueMessage(): string {
		return 'refund';
	}
}
