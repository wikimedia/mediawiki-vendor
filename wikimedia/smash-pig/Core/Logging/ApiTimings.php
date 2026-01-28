<?php

namespace SmashPig\Core\Logging;

/**
 * The ApiTimings class provides utilities for generating tagged logging identifiers
 * and logging API timing details for performance tracking purposes.
 */
final class ApiTimings {

	public const API_TIMINGS_LOGGER_TAG = 'APITimings';

	/**
	 * Builds a standardised tag based on the provided processor, payment method, and API operation.
	 *
	 * @param string $processor The name of the processor. This should be a non-empty string.
	 * @param string $paymentMethod The name of the payment method. This should be a non-empty string.
	 * @param string $apiOperation The name of the API operation. This should be a non-empty string.
	 * @return string The constructed tag in the format: [processor|paymentMethod|apiOperation|request|time].
	 * @throws \InvalidArgumentException If any of the input parameters are empty.
	 */
	public static function buildTag( string $processor, string $paymentMethod, string $apiOperation ): string {
		$processor = strtolower( trim( $processor ) );
		$paymentMethod = strtolower( trim( $paymentMethod ) );
		$apiOperation = strtolower( trim( $apiOperation ) );

		foreach ( [
			'processor' => $processor,
			'paymentMethod' => $paymentMethod,
			'apiOperation' => $apiOperation
		] as $name => $value ) {
			if ( $value === '' ) {
				throw new \InvalidArgumentException( "ApiTimings::buildTag requires non-empty {$name}" );
			}
		}

		return '[' . implode( '|', [ $processor, $paymentMethod, $apiOperation, 'request', 'time' ] ) . ']';
	}

	/**
	 * Logs a timing event with a specified tag and elapsed time in seconds.
	 *
	 * @param string $tag The tag associated with the log entry.
	 * @param float $elapsedSeconds The elapsed time in seconds.
	 * @param array $context Additional context to be logged with the entry.
	 * @param TaggedLogger|null $taggedLogger An optional TaggedLogger instance to handle the logging. If null, a default logger will be used.
	 * @return void
	 */
	public static function log(
		string $tag,
		float $elapsedSeconds,
		array $context = [],
		?TaggedLogger $taggedLogger = null
	): void {
		$taggedLogger = $taggedLogger ?: Logger::getTaggedLogger( self::API_TIMINGS_LOGGER_TAG );
		$message = $tag . ' ' . number_format( $elapsedSeconds, 6 ) . 's';

		// Only pass context if it's not empty to avoid cluttering logs with empty arrays
		if ( empty( $context ) ) {
			$taggedLogger->info( $message );
		} else {
			$taggedLogger->info( $message, $context );
		}
	}
}
