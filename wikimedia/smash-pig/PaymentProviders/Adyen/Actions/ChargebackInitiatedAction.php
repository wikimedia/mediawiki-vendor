<?php namespace SmashPig\PaymentProviders\Adyen\Actions;

use SmashPig\Core\Actions\IListenerMessageAction;
use SmashPig\Core\DataStores\QueueWrapper;
use SmashPig\Core\Logging\TaggedLogger;
use SmashPig\Core\Messages\ListenerMessage;
use SmashPig\PaymentProviders\Adyen\ExpatriatedMessages\Chargeback;
use SmashPig\PaymentProviders\Adyen\ExpatriatedMessages\RequestForInformation;

/**
 * When any kind of chargeback initiated (or completion) message arrives, this will
 * be fired.
 */
class ChargebackInitiatedAction implements IListenerMessageAction {

	/**
	 * Map Adyen's fields to ours
	 *
	 * @param Chargeback $msg
	 * @return array $queueMsg
	 */
	public function normalizeChargebackForQueue( Chargeback $msg ): array {
		$queueMsg = [
			'gateway_refund_id' => $msg->pspReference,
			'gateway_parent_id' => $msg->parentPspReference,
			'gross_currency' => $msg->currency,
			'gross' => $msg->amount,
			'date' => strtotime( $msg->eventDate ),
			'gateway' => 'adyen',
			'type' => 'chargeback',
		];
		return $queueMsg;
	}

	public function execute( ListenerMessage $msg ): bool {
		$tl = new TaggedLogger( 'ChargebackInitiatedAction' );

		if ( $msg instanceof Chargeback ) {
			if ( $msg->success ) {
				$tl->info(
					"Adding chargeback for {$msg->currency} {$msg->amount} with psp reference {$msg->pspReference} and originalReference {$msg->parentPspReference}."
				);
				$queueMessage = $this->normalizeChargebackForQueue( $msg );
				QueueWrapper::push( 'refund', $queueMessage );
			} else {
				$tl->info(
					"Got a failed chargeback for {$msg->currency} {$msg->amount} with psp reference {$msg->pspReference} and originalReference {$msg->parentPspReference}. Doing nothing."
				);
			}

		} elseif ( $msg instanceof RequestForInformation ) {
			// Not sure if we have received this type of message or have it setup in Civi just yet
			$tl->warning(
				"Oh hai! We got a chargeback RequestForInformation on pspReference " .
				"'{$msg->pspReference}'! What do we do now?",
				$msg
			);
		}

		return true;
	}
}
