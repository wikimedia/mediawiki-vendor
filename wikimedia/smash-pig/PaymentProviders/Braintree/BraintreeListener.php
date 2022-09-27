<?php
namespace SmashPig\PaymentProviders\Braintree;

use Exception;
use SmashPig\Core\Context;
use SmashPig\Core\DataStores\QueueWrapper;
use SmashPig\Core\Http\IHttpActionHandler;
use SmashPig\Core\Http\Request;
use SmashPig\Core\Http\Response;
use SmashPig\Core\Logging\Logger;

class BraintreeListener implements IHttpActionHandler {

	protected $providerConfiguration;

	public function execute( Request $request, Response $response ) {
		$this->providerConfiguration = Context::get()->getProviderConfiguration();

		$requestValues = $request->getValues();

		// Don't store blank messages.
		if ( empty( $requestValues ) ) {
			Logger::info( 'Empty message, nothing to do' );
			return false;
		}

		// Don't store invalid messages.
		if ( empty( $requestValues['bt_signature'] ) || empty( $requestValues['bt_payload'] ) ) {
			Logger::info( 'INVALID Braintree IPN message: ' . print_r( $requestValues, true ) );
			return false;
		}

		$parsed = false;
		try {
			Logger::info( 'Validating Braintree Webhook notification' );
			$signature = $requestValues['bt_signature'];
			$payload = $requestValues['bt_payload'];
			$parsed = $this->providerConfiguration->object( 'signature-validator' )->parse( $signature,  $payload );
		} catch ( Exception $e ) {
			// Tried to validate a bunch of times and got nonsense responses.
			Logger::error( $e->getMessage() );
			// 403 should tell them to send it again later.
			$response->setStatusCode( Response::HTTP_FORBIDDEN, 'Failed verification' );
			return false;
		}

		if ( $parsed ) {
			Logger::info( 'Braintree signature confirms message is valid' );
			// we need a serializable job class here to add the payload to.
			// for now let's just send an array.
			$message = [
				'payload' => $parsed
			];
			QueueWrapper::push( 'jobs-braintree', $message );
			Logger::info(
				'Pushed new message to jobs-braintree: ' .
				print_r( $requestValues, true )
			);
			Logger::info( 'Finished processing listener request' );
			return true;
		}

		Logger::info( 'INVALID Braintree IPN message: ' . print_r( $requestValues, true ) );
		return false;
	}

}
