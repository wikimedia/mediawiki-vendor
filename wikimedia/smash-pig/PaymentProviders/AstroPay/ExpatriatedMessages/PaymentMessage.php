<?php namespace SmashPig\PaymentProviders\AstroPay\ExpatriatedMessages;

/**
 * Message indicating a successful payment
 */
class PaymentMessage extends AstroPayMessage {
	public function getDestinationQueue() {
		return 'donations';
	}
}
