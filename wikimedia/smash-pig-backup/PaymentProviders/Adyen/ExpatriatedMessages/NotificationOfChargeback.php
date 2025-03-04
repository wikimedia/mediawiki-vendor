<?php namespace SmashPig\PaymentProviders\Adyen\ExpatriatedMessages;

use SmashPig\Core\Logging\TaggedLogger;

/**
 * A NOTIFICATION_OF_CHARGEBACK message is sent as a preliminary stage
 * for a chargeback process. The chargeback is pending, but may still
 * be defended if needed.
 *
 * @package SmashPig\PaymentProviders\Adyen\ExpatriatedMessages
 */
class NotificationOfChargeback extends AdyenMessage {

	/**
	 * Just log the notification of chargeback. Might be nice to be able to
	 * send an email just to Donor Relations here, to give them a chance to
	 * do something about it.
	 *
	 * @return bool True if all actions were successful. False otherwise.
	 */
	public function runActionChain(): bool {
		$tl = new TaggedLogger( 'NotificationOfChargeback' );

		$tl->warning(
			"Chargeback proceedings initiated for original reference $this->parentPspReference with new reference $this->pspReference"
		);
		return true;
	}
}
