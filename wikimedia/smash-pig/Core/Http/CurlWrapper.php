<?php

namespace SmashPig\Core\Http;

use SmashPig\Core\Context;
use SmashPig\Core\Logging\Logger;
use SmashPig\Core\ProviderConfiguration;

class CurlWrapper {

	/**
	 * @var ProviderConfiguration
	 */
	protected $providerConfiguration;

	public function __construct() {
		$this->providerConfiguration = Context::get()->getProviderConfiguration();
	}

	/**
	 * @param string $url
	 * @param string $method
	 * @param array $responseHeaders
	 * @param array|string $data
	 * @param string|null $certPath
	 * @param string|null $certPassword
	 * @return array|null
	 * @throws HttpException
	 * @throws \SmashPig\Core\ConfigurationKeyException
	 */
	public function execute(
		string $url, string $method, array $responseHeaders, $data, $certPath = null, $certPassword = null
	) {
		if ( is_array( $data ) ) {
			$data = http_build_query( $data );
		}

		Logger::info( "Initiating cURL" );
		$ch = curl_init();

		if ( $this->providerConfiguration->val( 'curl/log-outbound' ) ) {
			Logger::debug( "Outbound data: '$data'" );
		}
		// Always capture the cURL output
		$curlDebugLog = fopen( 'php://temp', 'r+' );

		// add stream filter to filter out extraneous curl verbose log lines
		stream_filter_register( 'CurlDebugLogFilter', 'SmashPig\Core\Http\CurlDebugLogFilter' );
		stream_filter_append(
			$curlDebugLog,
			"CurlDebugLogFilter",
			STREAM_FILTER_WRITE,
			[ 'SmashPig\Core\Logging\Logger', 'debug' ]
		);

		$curlOptions = $this->getCurlOptions(
			$url, $method, $responseHeaders, $data, $curlDebugLog, $certPath, $certPassword
		);
		curl_setopt_array( $ch, $curlOptions );

		// TODO: log timing
		$loopCount = $this->providerConfiguration->val( 'curl/retries' );
		$tries = 0;
		$parsed = null;
		do {
			Logger::info(
				"Preparing to send {$method} request to {$url}"
			);
			// Execute the cURL operation
			$response = curl_exec( $ch );

			// Rewind the log stream to flush it.
			rewind( $curlDebugLog );

			if ( $response !== false ) {
				// The cURL operation was at least successful, what happened in it?
				Logger::debug( "cURL response completed" );

				$curlInfo = curl_getinfo( $ch );
				$parsed = $this->parseResponse( $response, $curlInfo );

				/**
				 * @var ResponseValidator
				 * FIXME what about providers where not all requests are validated
				 * the same way?
				 */
				$validator = $this->providerConfiguration->object( 'curl/validator' );
				$continue = $validator->shouldRetry( $parsed );

			} else {
				// Well the cURL transaction failed for some reason or another. Try again!
				$continue = true;

				$errno = curl_errno( $ch );
				$err = curl_error( $ch );

				Logger::warning(
					"cURL transaction to {$url} failed: ($errno) $err. "
				);
			}
			$tries++;
			if ( $tries >= $loopCount ) {
				if ( $continue ) {
					// We ran out of retries, but apparently still haven't got
					// anything good. Squawk.
					Logger::alert(
						"cURL transaction to {$url} failed {$loopCount} times! " .
						'Please see previous warning-level logs for details.'
					);
				}
				$continue = false;
			}
		} while ( $continue ); // End while cURL transaction hasn't returned something useful

		// Clean up and return
		curl_close( $ch );
		fclose( $curlDebugLog );

		if ( $response === false ) {
			// no valid response after multiple tries
			throw new HttpException(
				"{$method} request to {$url} failed $loopCount times."
			);
		}

		return $parsed;
	}

	protected function getCurlOptions( $url, $method, $headers, $data, $logStream, $certPath, $certPassword ) {
		$options = [
			CURLOPT_URL => $url,
			CURLOPT_USERAGENT => $this->providerConfiguration->val( 'curl/user-agent' ),
			CURLOPT_HEADER => 1,
			CURLOPT_RETURNTRANSFER => 1,
			CURLOPT_TIMEOUT => $this->providerConfiguration->val( 'curl/timeout' ),
			CURLOPT_FOLLOWLOCATION => 0,
			CURLOPT_SSL_VERIFYPEER => 1,
			CURLOPT_SSL_VERIFYHOST => 2,
			CURLOPT_FORBID_REUSE => true,
			CURLOPT_VERBOSE => true,
			CURLOPT_STDERR => $logStream,
		];
		switch ( $method ) {
			case 'PUT':
				$options[CURLOPT_PUT] = 1;
				break;
			case 'DELETE':
			case 'HEAD':
				$options[CURLOPT_CUSTOMREQUEST] = $method;
				break;
			case 'POST':
				$options[CURLOPT_POST] = 1;
				break;
			default:
				break;
		}
		if ( $data !== null ) {
			$options[CURLOPT_POSTFIELDS] = $data;
		}
		foreach ( $headers as $name => $value ) {
			$options[CURLOPT_HTTPHEADER][] = "$name: $value";
		}
		if ( $certPath !== null ) {
			$options[CURLOPT_SSLCERT] = $certPath;
			if ( $certPassword !== null ) {
				$options[CURLOPT_SSLCERTPASSWD] = $certPassword;
			}
		}
		return $options;
	}

	public static function parseResponse( string $response, array $curlInfo ): array {
		$header_size = $curlInfo['header_size'];
		$header = substr( $response, 0, $header_size );
		$body = substr( $response, $header_size );
		$header = str_replace( "\r", "", $header );
		$headerLines = explode( "\n", $header );
		$responseHeaders = [];
		foreach ( $headerLines as $line ) {
			if ( strstr( $line, ': ' ) !== false ) {
				$line = rtrim( $line );
				[ $name, $value ] = explode( ': ', $line, 2 );
				$responseHeaders[$name] = $value;
			}
		}
		return [
			'body' => $body,
			'headers' => $responseHeaders,
			'status' => (int)$curlInfo['http_code']
		];
	}
}
