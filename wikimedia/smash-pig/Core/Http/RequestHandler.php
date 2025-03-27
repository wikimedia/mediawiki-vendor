<?php namespace SmashPig\Core\Http;

use SmashPig\Core\Context;
use SmashPig\Core\GlobalConfiguration;
use SmashPig\Core\Logging\Logger;
use SmashPig\Core\ProviderConfiguration;
use Symfony\Component\HttpFoundation\ParameterBag;
use Symfony\Component\HttpFoundation\Response;

/**
 * Entry point for the base initialized SmashPig application. Expects the requested
 * URL in the format p=<original path>&<original parameters>. The path is required to
 * be in the format of 'configuration view'/'action'/'sub-actions'. IE: adyen/listener
 * or adyen/api/foo/bar/baz.
 *
 * This class will load the requested configuration view; and then look for the action
 * to have been registered under the 'endpoints' node.
 */
class RequestHandler {
	/**
	 * @return Response
	 * @throws \SmashPig\Core\ConfigurationKeyException
	 */
	public static function process(): Response {
		// --- Get the request and response objects
		$request = Request::createFromGlobals();
		$response = new Response();
		$response->setPrivate();

		// --- Break the request into parts ---
		$uri = $request->query->get( 'p', '' );
		$parts = explode( '/', $uri );

		$request->query->remove( 'p' );

		if ( count( $parts ) < 2 ) {
			$response->setStatusCode(
				Response::HTTP_FORBIDDEN,
				'Cannot process this request: bad URI format. A configuration node and an action is required'
			);
			return $response;
		}

		$view = array_shift( $parts );
		$action = array_shift( $parts );

		// --- Initialize core services ---
		$config = GlobalConfiguration::create();
		$providerConfig = ProviderConfiguration::createForProvider( $view, $config );
		Context::init( $config, $providerConfig );
		// FIXME: let's always initialize this with the context
		Logger::init(
			$providerConfig->val( 'logging/root-context' ),
			$providerConfig->val( 'logging/log-level' ),
			$providerConfig,
			Context::get()->getContextId()
		);

		if ( $providerConfig->nodeExists( 'disabled' ) && $providerConfig->val( 'disabled' ) ) {
			Logger::debug( '403 will be given for disabled view.', $uri );
			$response->setStatusCode( Response::HTTP_FORBIDDEN, "View '$view' disabled. Cannot continue." );
			return $response;
		}

		if ( $providerConfig->nodeExists( 'charset' ) ) {
			// recreate the request with a different input encoding
			// FIXME: This is only converting the POST values.  Also,
			// is there really no better way to do this?
			$decoded = rawurldecode( $request->getContent() );
			$content = mb_convert_encoding( $decoded, 'UTF-8', $providerConfig->val( 'charset' ) );

			parse_str( $content, $data );
			$request->request = new ParameterBag( $data );
		}

		set_error_handler( '\SmashPig\Core\Http\RequestHandler::lastChanceErrorHandler' );
		set_exception_handler( '\SmashPig\Core\Http\RequestHandler::lastChanceExceptionHandler' );
		register_shutdown_function( '\SmashPig\Core\Http\RequestHandler::shutdownHandler' );

		// Check to make sure there's even a point to continuing
		Logger::info( "Starting processing for request, configuration view: '$view', action: '$action'" );
		if ( !$providerConfig->nodeExists( "endpoints/$action" ) ) {
			Logger::debug( '403 will be given for unknown action on inbound URL.', $uri );
			$response->setStatusCode( Response::HTTP_FORBIDDEN, "Action '$action' not configured. Cannot continue." );
			return $response;
		}

		// Inform the request object of our security environment
		$trustedHeader = $providerConfig->val( 'security/ip-header-name' );
		$trustedHeaderSet = 0;
		if ( $trustedHeader ) {
			// Currently only support the 'X-Forwarded-For'
			if ( $trustedHeader === 'X-Forwarded-For' ) {
				$trustedHeaderSet = $trustedHeaderSet | Request::HEADER_X_FORWARDED_FOR;
			} else {
				throw new \RuntimeException( "Unsupported ip-header-name $trustedHeader" );
			}
		}
		$trustedProxies = $providerConfig->val( 'security/ip-trusted-proxies' );
		if ( $trustedProxies ) {
			$request->setTrustedProxies( $trustedProxies, $trustedHeaderSet );
		}

		// --- Actually get the endpoint object and start the request ---
		$endpointObj = $providerConfig->object( "endpoints/$action" );
		if ( $endpointObj instanceof IHttpActionHandler ) {
			$endpointObj->execute( $request, $response );
		} else {
			$str = "Requested action '$action' does not implement a known handler. Cannot continue.";
			Logger::debug( $str );
			$response->setStatusCode( Response::HTTP_INTERNAL_SERVER_ERROR, $str );
		}

		$code = $response->getStatusCode();
		if ( ( $code !== Response::HTTP_OK ) && ( $code !== Response::HTTP_FOUND ) ) {
			$response->setContent( '' );
		}
		return $response;
	}

	public static function shutdownHandler() {
		$lastError = error_get_last();
		if ( $lastError and $lastError['type'] === E_ERROR ) {
			Logger::alert( "Fatal error caught by shutdown handler. ({$lastError['type']}) {$lastError['message']} @ {$lastError['file']}:{$lastError['line']}" );
		}
	}

	public static function lastChanceErrorHandler( $errno, $errstr, $errfile = 'Unknown File',
		$errline = 'Unknown Line', $errcontext = null
	) {
		Logger::alert( "Last chance error handler fired. ($errno) $errstr @ $errfile:$errline", $errcontext );

		$response = new Response();
		$response->setPrivate();
		$response->setStatusCode( Response::HTTP_INTERNAL_SERVER_ERROR, "Unhandled internal server error." );
		$response->send();

		return false;
	}

	/**
	 * Hook from set_exception_handler(). Will clear output data, set the HTTP status to 500: Internal Error
	 * and then die.
	 *
	 * @param \Exception $ex The uncaught exception
	 */
	public static function lastChanceExceptionHandler( $ex ) {
		Logger::alert( "Last chance exception handler fired.", null, $ex );

		$response = new Response();
		$response->setPrivate();
		$response->setStatusCode( Response::HTTP_INTERNAL_SERVER_ERROR, "Unhandled internal server exception." );
		$response->send();
	}
}
