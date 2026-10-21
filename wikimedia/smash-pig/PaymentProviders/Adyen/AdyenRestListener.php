<?php

namespace SmashPig\PaymentProviders\Adyen;

use SmashPig\Core\Http\Request;
use SmashPig\Core\Listeners\ListenerSecurityException;
use SmashPig\Core\Listeners\RestListener;
use SmashPig\Core\Logging\Logger;
use SmashPig\Core\Messages\ListenerMessage;
use SmashPig\PaymentProviders\Adyen\ExpatriatedMessages\AdyenMessage;

class AdyenRestListener extends RestListener {

	protected function doMessageSecurity( ListenerMessage $msg ): bool {
		// HMAC signatures are checked in parseEnvelope(), on the raw items.
		// By the time we get here AdyenMessage has changed signed fields.
		return true;
	}

	protected function ackMessage( ListenerMessage $msg ): bool {
		// TODO: Implement ackMessage() method.
		return true;
	}

	protected function parseEnvelope( Request $request ): array {
		$rawRequest = $request->getRawRequest();
		// remove expiryDate from rawRequest for reason and additionalData
		// replace mm/yyyy with blank for logging
		$patterns = [ '/(\d{1,2})\\\\\/20(\d{2})/',
			'/^\s*{(\w+)}\s*=/' ];
		$replace = [ '', '$\1 =' ];
		Logger::getTaggedLogger( 'RawData' )->info( preg_replace( $patterns, $replace, $rawRequest ) );
		$decoded = json_decode( $rawRequest, true );
		$items = [];
		foreach ( $decoded['notificationItems'] ?? [] as $notification ) {
			$item = $notification['NotificationRequestItem'] ?? null;
			if ( is_array( $item ) ) {
				$items[] = $item;
			} else {
				Logger::warning( 'Skipping Adyen notification item that is not an object' );
			}
		}
		// Check every item before building any messages, so one bad item
		// rejects the whole request and nothing from it is processed.
		$this->validateSignatures( $items );
		$messages = [];
		foreach ( $items as $item ) {
			$messages[] = AdyenMessage::getInstanceFromJSON( $item );
		}
		return $messages;
	}

	/**
	 * When reject-notifications-without-hmac is false, failures are only
	 * logged and the notifications are processed anyway.
	 *
	 * @param array $items NotificationRequestItems as decoded from the request body
	 * @throws ListenerSecurityException if any item does not carry a valid signature
	 */
	private function validateSignatures( array $items ): void {
		$validator = new NotificationSignatureValidator( $this->c->val( 'accounts' ) );
		$reject = (bool)$this->c->val( 'reject-notifications-without-hmac' );
		foreach ( $items as $item ) {
			try {
				$validator->validateSignature( $item );
			} catch ( ListenerSecurityException $ex ) {
				$context = [
					'pspReference' => $item['pspReference'] ?? null,
					'eventCode' => $item['eventCode'] ?? null,
					'merchantAccountCode' => $item['merchantAccountCode'] ?? null,
					'remoteIp' => $this->request->getClientIp(),
				];
				if ( $reject ) {
					Logger::warning( 'Rejecting Adyen notification: ' . $ex->getMessage(), $context );
					throw $ex;
				}
				Logger::warning(
					'Adyen notification failed HMAC check, accepting it because ' .
					'reject-notifications-without-hmac is false: ' . $ex->getMessage(),
					$context
				);
			}
		}
	}

	protected function ackEnvelope() {
		echo '{"notificationResponse": "[accepted]"}';
	}
}
