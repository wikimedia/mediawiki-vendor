<?php namespace SmashPig\PaymentProviders\AstroPay;

use SmashPig\Core\Http\Request;
use SmashPig\Core\Listeners\ListenerDataException;
use SmashPig\Core\Listeners\ListenerSecurityException;
use SmashPig\Core\Listeners\RestListener;
use SmashPig\Core\Logging\Logger;
use SmashPig\Core\Messages\ListenerMessage;

/**
 * Responds to payment messages from AstroPay
 */
class AstroPayListener extends RestListener {

	protected $byResult = [
		'9' => 'SmashPig\PaymentProviders\AstroPay\ExpatriatedMessages\PaymentMessage',
		'8' => 'SmashPig\PaymentProviders\AstroPay\ExpatriatedMessages\ExpirationMessage',
	];

	protected function parseEnvelope( Request $request ) {
		$requestValues = $request->getValues();

		$secureLog = Logger::getTaggedLogger( 'RawData' );
		$secureLog->info( "Incoming message (raw)" . print_r( $requestValues, true ) );

		$messages = [];

		// Can't even check signature without these four
		$required = [ 'result', 'x_amount', 'x_invoice', 'x_control' ];
		$missing = array_diff( $required, array_keys( $requestValues ) );
		if ( count( $missing ) ) {
			$this->tempDlocalIpnRetryKiller( $request->getRawRequest() );
			$list = implode( ',', $missing );
			throw new ListenerDataException( "AstroPay message missing required key(s) $list." );
		}

		$result = $requestValues['result'];
		if ( array_key_exists( $result, $this->byResult ) ) {
			$klass = $this->byResult[$result];
			$message = new $klass();
			$message->constructFromValues( $requestValues );

			$secureLog->debug( "Found message ", $message );

			$messages[] = $message;
		} else {
			Logger::info( "Message ignored: result = {$result}" );
		}

		return $messages;
	}

	/**
	 * Validate message signature
	 *
	 * @param ListenerMessage $msg Message object to operate on
	 *
	 * @throws ListenerSecurityException on security violation
	 */
	protected function doMessageSecurity( ListenerMessage $msg ) {
		$login = $this->c->val( 'login' );
		$secret = $this->c->val( 'secret' );
		$signed = $login . $msg->getSignedString();
		$control = strtoupper(
			hash_hmac( 'sha256', pack( 'A*', $signed ), pack( 'A*', $secret ) )
		);

		if ( $control != $msg->getSignature() ) {
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

	/**
	 * The Astropay Listener is receiving a steady flow of new-style IPNs from dLocal servers. This was an accident on
	 * the dLocal side, but unfortunately, these failed requests are retried multiple times an hour if they do not
	 * receive a HTTP 200 response. Let's quiet these down by detecting the affected IPNs and serving them a 200
	 * response. This code is temporary and will need to be removed once the failmails quiet down.
	 *
	 * @see https://phabricator.wikimedia.org/T333599
	 *
	 * @param string $rawRequest
	 *
	 * @return void
	 */
	private function tempDlocalIpnRetryKiller( string $rawRequest ): void {
		if ( $this->tempCheckIfRequestContainsDlocalPaymentIDPrefix( $rawRequest ) ) {
			// extract out the payment ID
			preg_match( '/"id":"(R-648[^"]+)"/', $rawRequest, $matches );
			$extractedPaymentID = $matches[1];
			// see if it's one of the affected payment IDs and return early if so.
			$this->tempReturnEarly200IfAffectedDlocalPaymentId( $extractedPaymentID );
		}
	}

	/**
	 * @param string $rawRequest
	 * @return bool
	 */
	private function tempCheckIfRequestContainsDlocalPaymentIDPrefix( string $rawRequest ): bool {
		return preg_match( '/"id":"(R-648[^"]+)"/', $rawRequest );
	}

	/**
	 * @param string $matches
	 * @return void
	 */
	private function tempReturnEarly200IfAffectedDlocalPaymentId( string $matches ): void {
		$badDlocalIpnPaymentIDs = [
			'R-648-d5f8928d-20c7-4726-ba22-22859b063efb',
			'R-648-da47377a-f927-407b-b663-7f014266d5f6',
		];

		if ( in_array( $matches, $badDlocalIpnPaymentIDs ) ) {
			$this->response->send();
			exit();
		}
	}
}
