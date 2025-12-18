<?php

namespace SmashPig\PaymentProviders\Gravy;

use Gr4vy\Gr4vyConfig;
use SmashPig\Core\Logging\TaggedLogger;

/**
 * Wrapper for timing and logging errors in Gravy SDK calls.
 *
 * Passes all calls through to the Gr4vyConfig object provided in
 * the constructor. Calls need an extra first argument to be used
 * as the unique ID for logging. All arguments beyond the first
 * are passed through to the SDK.
 */
class GravySDKWrapper {
	public function __construct( protected readonly Gr4vyConfig $gravySDK ) {
	}

	public function __call( $name, $arguments ) {
		$uniqueID = $arguments[0];
		array_shift( $arguments );
		$startTime = microtime( true );
		$response = $this->gravySDK->$name( ...$arguments );
		$endTime = microtime( true );
		$elapsedTime = $endTime - $startTime;
		$tl = new TaggedLogger( 'APITimings' );
		$tl->info( "fn: $name, elapsed: $elapsedTime s" );
		// Log elapsed time to file
		return self::handleGravySDKResponse( $uniqueID, $response, $name );
	}

	/**
	 * Handle Gravy SDK error responses (null, string, or unexpected types)
	 *
	 * @param ?string $uniqueIdentifier
	 * @param array|string|null $response
	 * @param string $functionName
	 * @return array|string[]
	 */
	public static function handleGravySDKResponse( ?string $uniqueIdentifier, null|array|string $response, string $functionName ): array {
		$tl = new TaggedLogger( 'RawData' );
		$preMessage = "{$functionName} response: " . ( $uniqueIdentifier ? "($uniqueIdentifier) " : "" );
		// Handle Gravy SDK error responses (null, string, or unexpected types)
		if ( $response === null ) {
			$errorMessage = $preMessage . "No response";
		} elseif ( is_string( $response ) ) {
			$errorMessage = $preMessage . $response;
		} elseif ( !is_array( $response ) ) {
			$errorMessage = $preMessage . "Unexpected response";
		}

		if ( isset( $errorMessage ) ) {
			$tl->info( $errorMessage );
			// simulate a Gravy-style error for our error mapper
			return [
				'type' => 'error',
				'message' => $errorMessage
			];
		}

		// Handle successful array response
		$formattedResponse = json_encode( $response );
		$tl->info( $preMessage . $formattedResponse );
		return $response;
	}
}
