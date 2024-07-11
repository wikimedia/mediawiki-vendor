<?php namespace SmashPig\PaymentProviders\PayPal;

use Exception;
use SmashPig\Core\Context;
use SmashPig\Core\DataStores\QueueWrapper;
use SmashPig\Core\Http\IHttpActionHandler;
use SmashPig\Core\Http\Request;
use SmashPig\Core\Http\Response;
use SmashPig\Core\Logging\Logger;

class Listener implements IHttpActionHandler {

	protected $providerConfiguration;

	public function execute( Request $request, Response $response ) {
		$this->providerConfiguration = Context::get()->getProviderConfiguration();

		$requestValues = $request->getValues();

		// Don't store blank messages.
		if ( empty( $requestValues ) ) {
			Logger::info( 'Empty message, nothing to do' );
			return false;
		}

		$valid = false;
		try {
			Logger::info( 'Validating message' );
			$valid = $this->providerConfiguration->object( 'ipn-validator' )->validate( $requestValues );
		} catch ( Exception $e ) {
			// Tried to validate a bunch of times and got nonsense responses.
			Logger::error( $e->getMessage() );
			// 403 should tell them to send it again later.
			$response->setStatusCode( Response::HTTP_FORBIDDEN, 'Failed verification' );
			return false;
		}

		if ( $valid ) {
			Logger::info( 'PayPal confirms message is valid' );
			QueueWrapper::push( 'jobs-paypal', [
				'class' => 'SmashPig\PaymentProviders\PayPal\Job',
				'payload' => $requestValues
			] );
			Logger::info( 'Pushed new message to jobs-paypal: ' .
				json_encode( $requestValues ) );
			Logger::info( 'Finished processing listener request' );
			return true;
		}

		Logger::info( 'INVALID IPN message: ' . json_encode( $requestValues ) );
		return false;
	}

}
