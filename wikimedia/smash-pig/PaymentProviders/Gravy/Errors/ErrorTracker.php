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
	public const GRAVY_FRAUD_LIST_EXPIRY_TIME = 3600;
	public const SUSPECTED_FRAUD_ERROR_CODE = 'suspected_fraud';

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

				// Check to see if we also need to send a fraud transaction list to donor relations
				if ( $this->isFraudAlert( $error['error_code'] ) ) {
					$fraudTransactionsData = $this->getFraudTransactionDataForTimeSlot( $this->getCurrentTimeSlot() );
					ErrorHelper::sendFraudTransactionsEmail( $fraudTransactionsData );
				}
			}
			return true;
		}

		// return false if $count !> 0 as something must have gone wrong
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

			// Track fraud transaction IDs separately
			$this->trackFraudTransactionIfApplicable( $error );

			return $currentCount;
		} catch ( \Exception $ex ) {
			Logger::warning( 'Failed to track error in Redis', [
				'error_code' => $error['error_code'],
				'exception' => $ex->getMessage()
			] );
			return 0;
		}
	}

	/**
	 * Track fraud transaction IDs in a separate Redis list for suspected_fraud errors
	 * Groups by same time buckets as error counters for relevance
	 */
	protected function trackFraudTransactionIfApplicable( array $error ): void {
		if ( !$this->isFraudAlert( $error['error_code'] ) ) {
			return;
		}

		if ( !isset( $error['sample_transaction_id'] ) ) {
			Logger::warning( 'suspected_fraud error missing transaction_id', [
				'error' => $error
			] );
			return;
		}

		try {
			$fraudListKey = $this->generateFraudTransactionListKey();

			// Get the current list length before adding
			$listLength = $this->connection->llen( $fraudListKey );

			// Add transaction data to list
			$transactionData = json_encode( [
				'id' => $error['sample_transaction_id'],
				'info' => $error['sample_data'] ?? ''
			], JSON_THROW_ON_ERROR );

			$this->connection->lpush( $fraudListKey, $transactionData );

			// Only set expiration on the first transaction in this time bucket
			if ( $listLength === 0 ) {
				$this->connection->expire( $fraudListKey, self::GRAVY_FRAUD_LIST_EXPIRY_TIME );
			}

			Logger::info( 'Fraud transaction tracked in redis', [
				'transaction_id' => $error['sample_transaction_id'],
				'error_code' => $error['error_code'],
				'time_bucket' => $this->getCurrentTimeSlot()
			] );
		} catch ( \Exception $ex ) {
			Logger::warning( 'Failed to track fraud transaction in Redis', [
				'transaction_id' => $error['transaction_id'],
				'exception' => $ex->getMessage()
			] );
		}
	}

	/**
	 * Get fraud transaction data for a specific time bucket
	 */
	protected function getFraudTransactionDataForTimeSlot( int $timeSlot ): array {
		try {
			if ( !$this->connection ) {
				$this->connection = $this->createRedisClient();
			}

			$fraudListKey = "{$this->keyPrefix}fraud_transactions:{$timeSlot}";
			$fraudTransactions = [];
			$serializedTransactions = $this->connection->lrange( $fraudListKey, 0, -1 );
			foreach ( $serializedTransactions as $data ) {
				$fraudTransactions[] = json_decode( $data, true, 512, JSON_THROW_ON_ERROR );
			}
			return $fraudTransactions;
		} catch ( \Exception $ex ) {
			Logger::warning( 'Failed to retrieve fraud transaction IDs from Redis', [
				'time_slot' => $timeSlot,
				'exception' => $ex->getMessage()
			] );
			return [];
		}
	}

	/**
	 * Updated method to use extracted time slot calculation
	 */
	protected function generateRedisErrorKey( string $errorCode ): string {
		$sanitizedErrorCode = preg_replace( '/\W/', '_', $errorCode );
		$currentTimeSlotInSeconds = $this->getCurrentTimeSlot();
		return "{$this->keyPrefix}{$sanitizedErrorCode}:{$currentTimeSlotInSeconds}";
	}

	protected function generateRedisAlertKey( string $errorCode ): string {
		return $this->generateRedisErrorKey( $errorCode ) . ':alerted';
	}

	/**
	 * Generate Redis key for fraud transaction list using same time bucketing as error counters
	 */
	protected function generateFraudTransactionListKey(): string {
		$currentTimeSlotInSeconds = $this->getCurrentTimeSlot();
		return "{$this->keyPrefix}fraud_transactions:{$currentTimeSlotInSeconds}";
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
	 * Get current time slot (extracted for reuse)
	 */
	protected function getCurrentTimeSlot(): int {
		return (int)floor( time() / $this->timeWindow );
	}

	protected function isThresholdExceeded( int $count ): bool {
		return $count >= $this->threshold;
	}

	/**
	 * @param string $error_code
	 * @return bool
	 */
	protected function isFraudAlert( string $error_code ): bool {
		return $error_code === self::SUSPECTED_FRAUD_ERROR_CODE;
	}
}
