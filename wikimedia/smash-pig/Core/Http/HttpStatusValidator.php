<?php

namespace SmashPig\Core\Http;

use SmashPig\Core\Logging\Logger;

/**
 * Determines whether to retry HTTP requests based on status code
 */
class HttpStatusValidator implements ResponseValidator {

	public function shouldRetry( array $parsedResponse ): bool {
		$statusCode = $parsedResponse['status'];
		if ( array_search( $statusCode, $this->getSuccessCodes() ) !== false ) {
			Logger::debug( "Successful request" );
			return false;
		}
		$body = $parsedResponse['body'];
		switch ( $statusCode ) {
			case Response::HTTP_BAD_REQUEST:
				// Oh noes! Bad request.. BAD CODE, BAD BAD CODE!
				$continue = false;
				Logger::error( "Request returned (400) BAD REQUEST: $body" );
				break;

			case Response::HTTP_FORBIDDEN:
				// Hmm, forbidden? Maybe if we ask it nicely again...
				$continue = true;
				Logger::warning( "Request returned (403) FORBIDDEN: $body" );
				break;

			case Response::HTTP_BAD_GATEWAY:
				// Timed out between their front-line server and their
				// application server. Let's try again.
				Logger::warning( "Request returned (502) GATEWAY ERROR: $body" );
				$continue = true;
				break;

			case Response::HTTP_CONFLICT:
				// May mean a response with the same idempotency key is still
				// processing - try again and see if it's done yet!
				Logger::info( "Request returned (409) CONFLICT: $body" );
				$continue = true;
				break;

			default:    // No clue what happened... break out and log it
				$continue = false;
				Logger::error( "Request returned http status ($statusCode): $body" );
				break;
		}
		return $continue;
	}

	protected function getSuccessCodes(): array {
		return [
			Response::HTTP_OK, // Everything is AWESOME
			Response::HTTP_CREATED, // Also fine, and we created a thing
			Response::HTTP_NO_CONTENT, // No news is good news
		];
	}
}
