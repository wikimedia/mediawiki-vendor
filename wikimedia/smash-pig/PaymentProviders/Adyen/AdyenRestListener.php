<?php

namespace SmashPig\PaymentProviders\Adyen;

use SmashPig\Core\Http\Request;
use SmashPig\Core\Listeners\RestListener;
use SmashPig\Core\Messages\ListenerMessage;
use SmashPig\PaymentProviders\Adyen\ExpatriatedMessages\AdyenMessage;

class AdyenRestListener extends RestListener {

	protected function doMessageSecurity( ListenerMessage $msg ) {
		// TODO: Implement doMessageSecurity() method.
	}

	protected function ackMessage( ListenerMessage $msg ) {
		// TODO: Implement ackMessage() method.
	}

	protected function parseEnvelope( Request $request ) {
		$rawRequest = $request->getRawRequest();
		$decoded = json_decode( $rawRequest, true );
		$messages = [];
		foreach ( $decoded['notificationItems'] as $notification ) {
			$messages[] = AdyenMessage::getInstanceFromJSON( $notification['NotificationRequestItem'] );
		}
		return $messages;
	}

	protected function ackEnvelope() {
		echo '{"notificationResponse": "[accepted]"}';
	}
}
