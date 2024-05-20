<?php

namespace SmashPig\PaymentProviders\Adyen;

use SmashPig\Core\Http\Request;
use SmashPig\Core\Listeners\RestListener;
use SmashPig\Core\Logging\Logger;
use SmashPig\Core\Messages\ListenerMessage;
use SmashPig\PaymentProviders\Adyen\ExpatriatedMessages\AdyenMessage;

class AdyenRestListener extends RestListener {

	protected function doMessageSecurity( ListenerMessage $msg ) {
		// TODO: Implement doMessageSecurity() method.
		return true;
	}

	protected function ackMessage( ListenerMessage $msg ) {
		// TODO: Implement ackMessage() method.
		return true;
	}

	protected function parseEnvelope( Request $request ) {
		$rawRequest = $request->getRawRequest();
		// remove expiryDate from rawRequest for reason and additionalData
		// replace mm/yyyy with blank for logging
		$patterns = [ '/(\d{1,2})\\\\\/20(\d{2})/',
			'/^\s*{(\w+)}\s*=/' ];
		$replace = [ '', '$\1 =' ];
		Logger::getTaggedLogger( 'RawData' )->info( preg_replace( $patterns, $replace, $rawRequest ) );
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
