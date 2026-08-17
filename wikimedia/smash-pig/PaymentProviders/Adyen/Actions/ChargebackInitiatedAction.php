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
class ChargebackInitiatedAction extends BaseRefundAction implements IListenerMessageAction {

	public function execute( ListenerMessage $msg ): bool {
		$tl = new TaggedLogger( 'ChargebackInitiatedAction' );

		if ( $msg instanceof Chargeback ) {
			if ( $msg->success ) {
				if ( $msg->chargebackSchemeCode == 'ach' ) {
					$tl->info(
						"Adding donation modify for {$msg->currency} {$msg->amount} with psp reference {$msg->pspReference} and originalReference {$msg->parentPspReference}."
					);
					$queueMessage = $this->normalizeACHMessageForQueue( $msg );
					QueueWrapper::push( 'donations-modify', $queueMessage );
				} else {
					$tl->info(
						"Adding chargeback for {$msg->currency} {$msg->amount} with psp reference {$msg->pspReference} and originalReference {$msg->parentPspReference}."
					);
					$queueMessage = $this->normalizeMessageForQueue( $msg );
					QueueWrapper::push( 'refund', $queueMessage );
				}
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

	protected function getTypeForQueueMessage(): string {
		return 'chargeback';
	}

	protected function normalizeACHMessageForQueue( Chargeback $msg ): array {
		return [
			'contribution_status_id:name' => 'Cancelled',
			'gateway_txn_id' => $this->getGatewayParentId( $msg ),
			'payment_method' => $this->getPaymentMethod( $msg ),
			'order_id' => $this->getOrderID( $msg ),
			'gross_currency' => $msg->currency,
			'gross' => $msg->amount,
			'backend_processor' => 'adyen',
			'backend_processor_txn_id' => $this->getGatewayParentId( $msg ),
			'date' => strtotime( $msg->eventDate ),
			'gateway' => $msg->gateway,
			'reason' => $msg->reason,
			'can_retry' => $this->isRetryableReason( $msg->reason )
		];
	}

	/**
	 * Can we retry the failed payment at a later date?
	 *
	 * @param string|null $reason
	 * @return bool
	 */
	protected function isRetryableReason( ?string $reason ): bool {
		// Reasons we have seen so far:
		// * Account Closed
		// * Beneficiary or Account Holder Deceased
		// * No Account\/Unable to Locate Account
		// * Payment Stopped
		// * R01 Insufficient Funds
		// Only 'Insufficient Funds' seems retryable
		return $reason === 'R01 Insufficient Funds';
	}
}
