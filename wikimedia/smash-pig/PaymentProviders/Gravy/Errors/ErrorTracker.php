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

		$this->enabled = $options['enabled'];
		$this->threshold = $options['threshold'];
		$this->timeWindow = $options['time_window'];
		$this->keyPrefix = $options['key_prefix'];
		$this->keyExpiryPeriod = $options['key_expiry_period'];
	}

	public function trackErrorAndCheckThreshold( array $error ): bool {
		if ( !$this->enabled ) {
			return false;
		}

		$count = $this->trackError( $error );
		if ( $count > 0 ) {
			if ( $this->isThresholdExceeded( $count ) ) {
				$errorCode = $error['error_code'] ?? self::UNKNOWN_ERROR_CODE;
				ErrorHelper::raiseAlert( $errorCode, $count, $this->threshold, $this->timeWindow, $error );
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

			$cacheKey = $this->generateRedisErrorKey( $error['error_code'] ?? self::UNKNOWN_ERROR_CODE );
			$currentCount = $this->connection->incr( $cacheKey );

			if ( $currentCount === 1 ) {
				$this->connection->expire( $cacheKey, $this->keyExpiryPeriod );
			}

			return $currentCount;
		} catch ( \Exception $ex ) {
			Logger::warning( 'Failed to track error in Redis', [
				'error_code' => $error['error_code'] ?? self::UNKNOWN_ERROR_CODE,
				'exception' => $ex->getMessage()
			] );
			return 0;
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

	protected function generateRedisErrorKey( string $errorCode ): string {
		$currentTimeSlotInSeconds = floor( time() / $this->timeWindow );
		$redisErrorTrackingKey = "{$this->keyPrefix}{$errorCode}:{$currentTimeSlotInSeconds}";
		return $redisErrorTrackingKey;
	}

	protected function isThresholdExceeded( int $count ): bool {
		return $count >= $this->threshold;
	}
}
