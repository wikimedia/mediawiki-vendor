<?php namespace SmashPig\PaymentProviders\Amazon;

use SmashPig\Core\Http\Request;
use SmashPig\Core\Listeners\ListenerDataException;
use SmashPig\Core\Listeners\ListenerSecurityException;
use SmashPig\Core\Listeners\RestListener;
use SmashPig\Core\Logging\Logger;
use SmashPig\Core\Messages\ListenerMessage;

/**
 * Uses the Amazon SDK to parse incoming IPN messages
 */
class AmazonListener extends RestListener {
	protected $messageClasses = [
		'PaymentCapture' => [
			'Completed' => 'SmashPig\PaymentProviders\Amazon\ExpatriatedMessages\CaptureCompleted',
			'Declined' => 'SmashPig\PaymentProviders\Amazon\ExpatriatedMessages\CaptureDeclined',
		],
		'PaymentRefund' => [
			'Completed' => 'SmashPig\PaymentProviders\Amazon\ExpatriatedMessages\RefundCompleted',
			'Declined' => 'SmashPig\PaymentProviders\Amazon\ExpatriatedMessages\RefundDeclined',
		],
		'PaymentAuthorize' => [
			'Declined' => 'SmashPig\PaymentProviders\Amazon\ExpatriatedMessages\AuthorizationDeclined',
		]
	];

	protected function parseEnvelope( Request $request ) {
		// Symfony's framework gives us each header's value as an array
		// (to account for potential repeated headers?
		// IpnHandler's constructor expects scalar values, so we flatten them
		$headers = [];
		foreach ( $request->headers->all() as $header => $annoyingArray ) {
			if ( count( $annoyingArray ) !== 1 ) {
				throw new ListenerDataException( "header '$header' should have a single value" );
			}
			$headers[$header] = $annoyingArray[0];
		}
		$json = $request->getRawRequest();

		$secureLog = Logger::getTaggedLogger( 'RawData' );
		$secureLog->info(
			'Incoming message (raw)',
			[
				'headers' => $headers,
				'body' => $json
			]
		);

		$messages = [];
		try {
			$amazonHandlerMessage = AmazonApi::createIpnHandler(
				$headers,
				$json
			);
		} catch ( \Exception $ex ) {
			// FIXYOU: IpnHandler should use exception subclasses or error codes
			// Assuming here that IpnHandler's problem was with the signature
			// We can get away with throwing ListenerSecurityException here
			// because of how RestListener is implemented and because we only
			// process one message per request
			// Bad form, but it would be odd to hold this till doMessageSecurity
			Logger::alert(
				'Error validating Amazon message. Please ensure that your ' .
				'firewall allows outbound connections to all AWS IPs for Amazon ' .
				'services in US-East-1. This tool may be useful: ' .
				'https://phabricator.wikimedia.org/diffusion/WFTO/browse/master/amazon-allowlist/amazon-ranges.sh'
			);
			throw new ListenerSecurityException( $ex->getMessage() );
		}
		$messageValues = $amazonHandlerMessage->toArray();
		$type = $messageValues['NotificationType'];
		if ( array_key_exists( $type, $this->messageClasses ) ) {
			$byStatus = $this->messageClasses[$type];
			$status = $this->getMessageStatus( $messageValues, $type );
			if ( array_key_exists( $status, $byStatus ) ) {
				$klass = $byStatus[$status];
				$message = new $klass( $messageValues );
				$secureLog->debug( 'Created message', $message );
				$messages[] = $message;
			} else {
				Logger::info(
					"Message ignored: notificationType = {$type}, status = {$status}"
				);
			}
		} else {
			Logger::info( "Message ignored: notificationType = {$type}" );
		}

		return $messages;
	}

	protected function getMessageStatus( $values, $type ) {
		switch ( $type ) {
			case 'PaymentCapture':
				return $values['CaptureDetails']['CaptureStatus']['State'];
			case 'PaymentRefund':
				return $values['RefundDetails']['RefundStatus']['State'];
			case 'PaymentAuthorize':
				return $values['AuthorizationDetails']['AuthorizationStatus']['State'];
			default:
				return false;
		}
	}

	/**
	 * Stub, since IpnHandler validates the signature on the way in
	 *
	 * @param ListenerMessage $msg Message object to operate on
	 */
	protected function doMessageSecurity( ListenerMessage $msg ) {
		return true;
	}

	/**
	 * Stub-- maybe this is an egregious pure virtual function
	 */
	protected function ackMessage( ListenerMessage $msg ) {
		return true;
	}

	protected function ackEnvelope() {
		// pass
	}
}
