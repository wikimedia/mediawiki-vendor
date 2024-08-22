<?php
namespace SmashPig\PaymentProviders\Gravy;

use SmashPig\Core\Context;
use SmashPig\Core\DataStores\QueueWrapper;
use SmashPig\Core\Http\IHttpActionHandler;
use SmashPig\Core\Http\Request;
use SmashPig\Core\Http\Response;
use SmashPig\Core\Logging\Logger;
use SmashPig\PaymentProviders\Gravy\Validators\Validator;
use SmashPig\PaymentProviders\ValidationException;

class GravyListener implements IHttpActionHandler {

	protected $providerConfiguration;

	public function execute( Request $request, Response $response ) {
		$this->providerConfiguration = Context::get()->getProviderConfiguration();

		$requestValues = $request->getRawRequest();

		// Don't store blank messages.
		if ( empty( $requestValues ) ) {
			Logger::info( 'Empty message, nothing to do' );
			return false;
		}

		$parsed = null;
		$headers = $request->server->getHeaders();

		try {
			$validator = new Validator();
			$validator->validateWebhookEventHeader( $headers, $this->providerConfiguration );
			Logger::info( 'Received Gravy webhook notification' );
			$parsed = json_decode( $requestValues, true );
		} catch ( ValidationException $e ) {
			// Tried to validate a bunch of times and got nonsense responses.
			Logger::error( $e->getMessage() );

			$response->setStatusCode( Response::HTTP_FORBIDDEN, 'Invalid authorization' );
			return false;
		}
		catch ( \Exception $e ) {
			// Log exception
			Logger::error( $e->getMessage() );
			// 403 should tell them to send it again later.
			$response->setStatusCode( Response::HTTP_BAD_REQUEST, 'Failed to read message' );
			return false;
		}

		if ( $parsed ) {
			Logger::info( 'Moving Gravy webhook message to queue' );
			// we need a serializable job class here to add the payload to.
			// for now let's just send an array.
			$message = [
				'payload' => $parsed
			];
			// TODO: Create job classes for the different event types
			QueueWrapper::push( 'jobs-gravy', $message );
			Logger::info(
				'Pushed new message to jobs-gravy: ' .
				print_r( $requestValues, true )
			);
			Logger::info( 'Finished processing listener request' );
			return true;
		}

		Logger::info( 'INVALID Gravy IPN message: ' . print_r( $requestValues, true ) );
		return false;
	}

}
