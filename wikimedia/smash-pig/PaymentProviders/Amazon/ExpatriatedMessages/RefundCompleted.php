<?php namespace SmashPig\PaymentProviders\Amazon\ExpatriatedMessages;

class RefundCompleted extends PaymentRefund {
	public function getDestinationQueue() {
		return 'refund';
	}
}
