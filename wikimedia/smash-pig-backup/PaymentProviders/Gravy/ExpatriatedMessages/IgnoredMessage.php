<?php

namespace SmashPig\PaymentProviders\Gravy\ExpatriatedMessages;

abstract class IgnoredMessage extends GravyMessage {

	public function init( array $notification ): GravyMessage {
		return $this;
	}

	public function validate(): bool {
		return true;
	}

	public function getDestinationQueue(): ?string {
		return null;
	}

	public function getAction(): ?string {
		return null;
	}
}
