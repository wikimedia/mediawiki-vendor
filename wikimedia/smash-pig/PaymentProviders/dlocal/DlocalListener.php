<?php namespace SmashPig\PaymentProviders\dlocal;

use SmashPig\Core\Context;
use SmashPig\Core\Http\Request;
use SmashPig\Core\Listeners\ListenerDataException;
use SmashPig\Core\Listeners\ListenerSecurityException;
use SmashPig\Core\Listeners\RestListener;
use SmashPig\Core\Logging\Logger;
use SmashPig\Core\Messages\ListenerMessage;

/**
 * Responds to payment notifications from dlocal
 */
class DlocalListener extends RestListener {

	// Success status are refunds and completed status are chargebacks
	protected $paymentStatus = [
		'PAID' => 'SmashPig\PaymentProviders\dlocal\ExpatriatedMessages\PaidMessage',
		'SUCCESS' => 'SmashPig\PaymentProviders\dlocal\ExpatriatedMessages\SuccessMessage',
		'COMPLETED' => 'SmashPig\PaymentProviders\dlocal\ExpatriatedMessages\CompletedMessage',
	];

	/**
	 * @var \SmashPig\Core\ProviderConfiguration
	 */
	protected $providerConfiguration;

	protected function parseEnvelope( Request $request ) {
		$this->providerConfiguration = Context::get()->getProviderConfiguration();

		// dlocal sends us a json string with the request
		$rawRequest = $request->getRawRequest();
		Logger::info( 'Incoming message (raw): ' . print_r( $rawRequest, true ) );

		$authorizationHeader = $request->headers->get( 'authorization' );
		// We only want to process messages sent with an authorization header we can validate
		if ( empty( $authorizationHeader ) ) {
			$requestValues = $request->getValues();
			if ( isset( $requestValues['x_control'] ) ) {
				Logger::info( 'discarding mis-directed old-style IPN' );
				return [];
			}
			throw new ListenerDataException(
				'INVALID dlocal IPN message with no authorization header: ' . print_r( $rawRequest, true )
			);
		}

		$messages = [];

		$decoded = json_decode( $rawRequest, true );
		$status = $decoded['status'];
		if ( array_key_exists( $status, $this->paymentStatus ) ) {
			// add the signature input to the message for the later signature validation
			$login = $this->providerConfiguration->val( 'login' );
			$xdate = $request->headers->get( 'x-date' );
			$signatureInput = $login . $xdate . $rawRequest;
			$decoded['signatureInput'] = $signatureInput;
			$decoded['authorization'] = $authorizationHeader;

			Logger::debug( "Authorization header: '$authorizationHeader'; Login: '$login'; x-date header: '$xdate';" );

			$class = $this->paymentStatus[$status];
			$message = new $class();
			$message->constructFromValues( $decoded );

			$messages[] = $message;
		} else {
			Logger::info( "Message ignored: result = {$status}" );
		}

		return $messages;
	}

	/**
	 * Validate message signature
	 * https://docs.dlocal.com/docs/receive-notifications#signature-of-notifications
	 *
	 * @param ListenerMessage $msg Message object to operate on
	 *
	 * @throws ListenerSecurityException on security violation
	 */
	protected function doMessageSecurity( ListenerMessage $msg ) {
		$secret = $this->providerConfiguration->val( 'secret' );
		$signature = $this->providerConfiguration->object( 'signature-calculator' )->calculate( $msg->signatureInput, $secret );
		// the authorization header from dlocal has this text at the beginning
		$signature = 'V2-HMAC-SHA256, Signature: ' . $signature;
		if ( $msg->authorization != $signature ) {
			throw new ListenerSecurityException();
		}
		return true;
	}

	protected function ackMessage( ListenerMessage $msg ) {
		return true;
	}

	protected function ackEnvelope() {
		// pass
	}
}
