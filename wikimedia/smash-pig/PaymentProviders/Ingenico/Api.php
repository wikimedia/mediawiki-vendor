<?php

namespace SmashPig\PaymentProviders\Ingenico;

use DateTime;
use DateTimeZone;
use SmashPig\Core\ApiException;
use SmashPig\Core\Context;
use SmashPig\Core\Helpers\UniqueId;
use SmashPig\Core\Http\OutboundRequest;
use SmashPig\Core\Logging\Logger;
use Symfony\Component\HttpFoundation\Response;

/**
 * Prepares and sends requests to the Ingenico Connect API.
 */
class Api {

	const API_VERSION = 'v1';

	/**
	 * @var Authenticator
	 */
	protected $authenticator;

	protected $baseUrl;

	protected $merchantId;

	/**
	 * Api constructor.
	 *
	 * @param string $baseUrl
	 * @param string $merchantId
	 *
	 * @throws \SmashPig\Core\ConfigurationKeyException
	 */
	public function __construct( string $baseUrl, string $merchantId ) {
		// Ensure trailing slash
		if ( substr( $baseUrl, -1 ) !== '/' ) {
			$baseUrl .= '/';
		}
		$this->baseUrl = $baseUrl;
		$this->merchantId = $merchantId;
		// FIXME: provide objects in constructor
		$config = Context::get()->getProviderConfiguration();
		$this->authenticator = $config->object( 'authenticator' );
	}

	/**
	 * @param string $path
	 * @param string $method
	 * @param array|null $data
	 * @param bool $idempotent
	 * @return array|null
	 * @throws ApiException
	 */
	public function makeApiCall(
		string $path, string $method = 'POST', ?array $data = null, bool $idempotent = false
	) {
		if ( is_array( $data ) ) {
			// FIXME: this is weird, maybe OutboundRequest should handle this part
			if ( $method === 'GET' ) {
				$path .= '?' . http_build_query( $data );
				$data = null;
			} else {
				$originalData = $data;
				// No need to use \u00e1 escaping which might expand data elements past limits.
				$data = json_encode( $data, JSON_UNESCAPED_UNICODE );
				// additional logging to catch any json_encode failures.
				if ( $data === false ) {
					$jsonError = json_last_error_msg();
					Logger::debug(
						"Unable to json_encode() request params. (" . $jsonError . ") (data: " . print_r( $originalData, true ) . ")",
						$originalData
					);
				}
			}
		}
		$url = $this->baseUrl . self::API_VERSION . "/{$this->merchantId}/$path";
		$request = new OutboundRequest( $url, $method );
		$request->setBody( $data );
		if ( $method !== 'GET' ) {
			$request->setHeader( 'Content-Type', 'application/json' );
		}
		// Set date header manually so we can use it in signature generation
		$date = new DateTime( 'now', new DateTimeZone( 'UTC' ) );
		$request->setHeader( 'Date', $date->format( 'D, d M Y H:i:s T' ) );

		// https://epayments-api.developer-ingenico.com/s2sapi/v1/en_US/go/idempotent-requests.html
		if ( $idempotent ) {
			$request->setHeader( 'X-GCS-Idempotence-Key', UniqueId::generate() );
		}

		// set more headers...
		$this->authenticator->signRequest( $request );

		$response = $request->execute();
		$decodedResponseBody = json_decode( $response['body'], true );
		$expectedEmptyBody = ( $response['status'] === Response::HTTP_NO_CONTENT );

		if ( !$expectedEmptyBody && empty( $decodedResponseBody ) ) {
			throw new ApiException(
				"Response body is empty or not valid JSON: '{$response['body']}'"
			);
		}

		return $decodedResponseBody;
	}

}
