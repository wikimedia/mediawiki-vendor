<?php namespace SmashPig\PaymentProviders\Amazon\ExpatriatedMessages;

class CaptureCompleted extends PaymentCapture {
	public function getDestinationQueue() {
		return 'jobs-amazon';
	}
}
