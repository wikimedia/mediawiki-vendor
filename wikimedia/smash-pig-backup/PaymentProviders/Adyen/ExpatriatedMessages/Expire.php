<?php namespace SmashPig\PaymentProviders\Adyen\ExpatriatedMessages;

use SmashPig\Core\Logging\TaggedLogger;

class Expire extends AdyenMessage {
	/**
	 * We shouldn't be getting an expire message, we should cancel or complete
	 * the auth before we get to this status
	 *
	 * @return bool True if all actions were successful. False otherwise.
	 */
	public function runActionChain(): bool {
		$tl = new TaggedLogger( 'Expire' );

		$tl->alert(
			"Expire messaged received for $this->pspReference"
		);
		return true;
	}
}
