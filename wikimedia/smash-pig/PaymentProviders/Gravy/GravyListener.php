<?php
namespace SmashPig\PaymentProviders\Gravy;

use SmashPig\Core\Context;
use SmashPig\Core\Http\IHttpActionHandler;
use SmashPig\Core\Http\Request;
use SmashPig\Core\Http\Response;
use SmashPig\Core\Logging\Logger;
use SmashPig\PaymentProviders\Gravy\Actions\GravyAction;
use SmashPig\PaymentProviders\Gravy\ExpatriatedMessages\GravyMessage;
use SmashPig\PaymentProviders\Gravy\Validators\Validator;
use SmashPig\PaymentProviders\ValidationException;

class GravyListener implements IHttpActionHandler {

	protected $providerConfiguration;

	public function execute( Request $request, Response $response ): bool {
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

			if ( $parsed ) {
				Logger::info( 'Processing Gravy webhook message to queue' );

				$normalizedNotification = $this->mapFromWebhookMessage( $parsed );
				$message = GravyMessage::getInstanceFromNormalizedNotification( $normalizedNotification );

				if ( $message ) {
					$action = GravyAction::getInstanceOf( $message->getAction() );
					$action->execute( $message );
					Logger::info( 'Finished processing listener request' );
					return true;
				}

			}
		} catch ( ValidationException $e ) {
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

		Logger::info( 'INVALID Gravy IPN message: ' . print_r( $requestValues, true ) );
		return false;
	}

	/**
	 * Normalize the webhook message to pick out the useful info
	 * @param array $message
	 * @return array
	 */
	private function mapFromWebhookMessage( array $message ) {
		return [
			'created_at' => $message["created_at"],
			'id' => $message["target"]["id"],
			'message_type' => $this->normalizeMessageType( $message["target"]["type"] )
		];
	}

	/**
	 * @param string $type
	 * @return string
	 * @throws \UnexpectedValueException
	 */
	private function normalizeMessageType( string $type ): string {
		switch ( $type ) {
			case 'transaction':
				return 'TransactionMessage';
			default:
				throw new \UnexpectedValueException( "Listener received unknown message type $type" );
		}
	}
}
