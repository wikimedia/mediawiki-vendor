<?php

namespace SmashPig\PaymentProviders\Gravy\Errors;

use InvalidArgumentException;
use Predis\Client;
use SmashPig\Core\Context;
use SmashPig\Core\Logging\Logger;

/**
 * Class ErrorTracker
 *
 * Tracks errors and raises alerts if an error count threshold is exceeded
 * within a defined time window. Uses Redis for error counting and storage.
 */
class ErrorTracker {
	public const UNKNOWN_ERROR_CODE = 'unknown_error_code';
	protected bool $enabled;
	protected int $threshold;
	protected int $timeWindow;
	protected string $keyPrefix;
	protected int $keyExpiryPeriod;
	protected int $alertSuppressionPeriod;
	protected array $ignoreList;
	protected ?Client $connection = null;

	/**
	 * Constructs a new instance of the class with the provided options.
	 *
	 * @param array $options An associative array of configuration options:
	 *                       - 'enabled' (bool): Specifies whether the component is enabled.
	 *                       - 'threshold' (int): The threshold value for errors.
	 *                       - 'time_window' (int): The time window for measuring errors in seconds.
	 *                       - 'key_prefix' (string): The redis prefix to be used for keys.
	 *                       - 'key_expiry_period' (int): The expiry period for redis keys in seconds.
	 *                       - 'alert_suppression_period' (int): The period in seconds to suppress duplicate alerts.
	 *                       - 'ignore_list' (array): List of error codes to ignore from tracking and alerting.
	 *
	 * @return void
	 */
	public function __construct( array $options = [] ) {
		if ( $options['threshold'] <= 0 ) {
			throw new InvalidArgumentException( 'ErrorTracker Threshold must be positive' );
		}
		if ( $options['time_window'] <= 0 ) {
			throw new InvalidArgumentException( 'ErrorTracker Time window must be positive' );
		}
		if ( $options['key_expiry_period'] <= 0 ) {
			throw new InvalidArgumentException( 'ErrorTracker key expiry window must be positive' );
		}
		if ( $options['alert_suppression_period'] <= 0 ) {
			throw new InvalidArgumentException( 'ErrorTracker alert suppression period must be positive' );
		}

		$this->enabled = $options['enabled'];
		$this->threshold = $options['threshold'];
		$this->timeWindow = $options['time_window'];
		$this->keyPrefix = $options['key_prefix'];
		$this->keyExpiryPeriod = $options['key_expiry_period'];
		$this->alertSuppressionPeriod = $options['alert_suppression_period'];
		$this->ignoreList = $options['ignore_list'] ?? [];
	}

	public function trackErrorAndCheckThreshold( array $error ): bool {
		if ( !$this->enabled ) {
			return false;
		}
		// We need an error_code to work with
		if ( !isset( $error['error_code'] ) ) {
			Logger::info( 'Error code is missing in error array', [
				'error' => $error
			] );
			return false;
		}
		// Skip tracking if error code is in the ignore list
		if ( in_array( strtolower( $error['error_code'] ), array_map( 'strtolower', $this->ignoreList ), true ) ) {
			Logger::info( 'Skipping error tracking - error code is in ignore list', [
				'error_code' => $error['error_code']
			] );
			return false;
		}

		$count = $this->trackError( $error );
		if ( $count > 0 ) {
			if ( $this->isThresholdExceeded( $count ) && !$this->alertRecentlySent( $error['error_code'] ) ) {
				ErrorHelper::raiseAlert( $error['error_code'], $count, $this->threshold, $this->timeWindow, $error );
				$this->markAlertAsSent( $error['error_code'] );
			}
			return true;
		}

		return false;
	}

	protected function trackError( array $error ): int {
		try {
			if ( !$this->connection ) {
				$this->connection = $this->createRedisClient();
			}

			$cacheKey = $this->generateRedisErrorKey( $error['error_code'] );
			$currentCount = $this->connection->incr( $cacheKey );

			if ( $currentCount === 1 ) {
				$this->connection->expire( $cacheKey, $this->keyExpiryPeriod );
			}

			return $currentCount;
		} catch ( \Exception $ex ) {
			Logger::warning( 'Failed to track error in Redis', [
				'error_code' => $error['error_code'],
				'exception' => $ex->getMessage()
			] );
			return 0;
		}
	}

	protected function alertRecentlySent( string $errorCode ): bool {
		try {
			if ( !$this->connection ) {
				$this->connection = $this->createRedisClient();
			}

			$alertKey = $this->generateRedisAlertKey( $errorCode );
			return $this->connection->exists( $alertKey );
		} catch ( \Exception $ex ) {
			Logger::warning( 'Failed to check alert suppression key in Redis', [
				'error_code' => $errorCode,
				'exception' => $ex->getMessage()
			] );
			return false;
		}
	}

	protected function markAlertAsSent( string $errorCode ): void {
		try {
			if ( !$this->connection ) {
				$this->connection = $this->createRedisClient();
			}

			$alertKey = $this->generateRedisAlertKey( $errorCode );
			$this->connection->setex( $alertKey, $this->alertSuppressionPeriod, '1' );
		} catch ( \Exception $ex ) {
			Logger::warning( 'Failed to set alert suppression key in Redis', [
				'error_code' => $errorCode,
				'exception' => $ex->getMessage()
			] );
		}
	}

	protected function createRedisClient(): Client {
		$globalConfig = Context::get()->getGlobalConfiguration();
		$redisConfig = $globalConfig->val( 'redis' );

		// Extract servers configuration
		$servers = $redisConfig['servers'] ?? [];

		// Extract any other Redis options (excluding 'servers')
		$options = array_filter( $redisConfig, static function ( $key ) {
			return $key !== 'servers';
		}, ARRAY_FILTER_USE_KEY );

		return new Client( $servers, $options );
	}

	/**
	 * Generates a Redis key to track error occurrences based on the error code and the current time window.
	 * For example, with a 30-minute time window (1800 seconds):
	 * If the current timestamp is 1623456789, then 1623456789/1800 = 901920.43
	 * floor() gives us 901920, so all errors within this 30-minute window
	 * will have the same time slot number in the Redis key
	 *
	 * These keys expire automatically so we don't need to clean them up
	 *
	 * @param string $errorCode The specific error code to include in the Redis key.
	 *
	 * @return string The generated Redis key, which includes the key prefix, error code, and the current time slot.
	 */
	protected function generateRedisErrorKey( string $errorCode ): string {
		// Sanitise error code: remove special characters and spaces, keep only alphanumeric and underscore
		$sanitizedErrorCode = preg_replace( '/\W/', '_', $errorCode );
		$currentTimeSlotInSeconds = floor( time() / $this->timeWindow );
		return "{$this->keyPrefix}{$sanitizedErrorCode}:{$currentTimeSlotInSeconds}";
	}

	protected function generateRedisAlertKey( string $errorCode ): string {
		return $this->generateRedisErrorKey( $errorCode ) . ':alerted';
	}

	protected function isThresholdExceeded( int $count ): bool {
		return $count >= $this->threshold;
	}
}
