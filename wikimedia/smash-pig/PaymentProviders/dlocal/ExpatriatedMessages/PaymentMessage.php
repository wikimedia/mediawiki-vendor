<?php namespace SmashPig\PaymentProviders\dlocal\ExpatriatedMessages;

/**
 * Message indicating a successful payment
 */
class PaymentMessage extends DlocalMessage {
	public function getDestinationQueue() {
		return 'jobs-dlocal';
	}
}
