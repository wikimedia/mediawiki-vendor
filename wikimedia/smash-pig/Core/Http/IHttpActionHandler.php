<?php namespace SmashPig\Core\Http;

use Symfony\Component\HttpFoundation\Response;

/**
 * Declaration that a class is able to process an HTTP request.
 */
interface IHttpActionHandler {
	/**
	 * Execute an arbitrary action based on the inbound $request object.
	 *
	 * @param Request $request HTTP request context object
	 * @param Response $response HTTP response data object
	 *
	 * @return null
	 */
	public function execute( Request $request, Response $response );
}
