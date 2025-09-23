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
				// Check to see if we need to send a fraud transaction list to donor relations
				if ( $this->isFraudAlert( $error['error_code'] ) ) {
					$fraudTransactionsData = $this->getFraudTransactionDataForTimeSlot( $this->getCurrentTimeSlot() );
					ErrorHelper::sendFraudTransactionsEmail( $fraudTransactionsData );
				} else {
					// Just send the standard failmail warning of a repeated error
					ErrorHelper::raiseAlert(
						$error['error_code'], $count, $this->threshold, $this->timeWindow, $error
					);
				}
				$this->markAlertAsSent( $error['error_code'] );
			}
			return true;
		}

		// return false if $count !> 0 as something must have gone wrong
		return false;
	}

	/**
	 * Tracks an error transaction, stores relevant data in a Redis set, and logs the process.
	 *
	 * @param array $error An associative array containing error details.
	 *                     Expected keys:
	 *                     - 'error_code' (string): The error code identifying the error.
	 *                     - 'sample_transaction_id' (string|null): Optional unique transaction ID.
	 *                     - 'sample_transaction_summary' (string): Optional summary of the transaction.
	 *
	 * @return int The current count of error transactions associated with the specified error code.
	 *             Returns 0 if the transaction could not be tracked.
	 */
	protected function trackError( array $error ): int {
		try {
			if ( !$this->connection ) {
				$this->connection = $this->createRedisClient();
			}

			$redisSetKey = $this->generateRedisErrorKey( $error['error_code'] );
			$transactionId = $error['sample_transaction_id'] ?? null;
			$transactionSummary = $error['sample_transaction_summary'] ?? null;

			if ( !$transactionId ) {
				Logger::warning( 'Error missing transaction_id', [
					'error_code' => $error['error_code']
				] );
			}

			// Serialize transaction data for redis
			$errorTransactionData = json_encode( [
				'id' => $transactionId,
				'summary' => $transactionSummary
			], JSON_THROW_ON_ERROR );

			// SADD returns 1 if new member, 0 if already exists
			$wasAdded = $this->connection->sadd( $redisSetKey, [ $errorTransactionData ] );
			$currentCount = $this->connection->scard( $redisSetKey );

			if ( $wasAdded ) {
				Logger::info( 'Error transaction tracked in redis', [
					'transaction_id' => $transactionId,
					'error_code' => $error['error_code'],
					'time_bucket' => $this->getCurrentTimeSlot(),
					'current_count' => $currentCount
				] );
			} else {
				Logger::info( 'Transaction already tracked for this error code in current time bucket', [
					'transaction_id' => $transactionId,
					'error_code' => $error['error_code'],
					'time_bucket' => $this->getCurrentTimeSlot(),
					'current_count' => $currentCount
				] );
			}

			// Set expiration on first entry (when count is 1 after adding)
			if ( $currentCount === 1 ) {
				$this->connection->expire( $redisSetKey, $this->keyExpiryPeriod );
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

	/**
	 * Get fraud transaction data for a specific time bucket using sets
	 */
	protected function getFraudTransactionDataForTimeSlot( int $timeSlot ): array {
		try {
			if ( !$this->connection ) {
				$this->connection = $this->createRedisClient();
			}

			$fraudSetKey = $this->keyPrefix . self::SUSPECTED_FRAUD_ERROR_CODE . ":{$timeSlot}";
			$fraudTransactions = [];
			$serializedTransactions = $this->connection->smembers( $fraudSetKey );

			foreach ( $serializedTransactions as $data ) {
				$decoded = json_decode( $data, true, 512, JSON_THROW_ON_ERROR );
				if ( $decoded ) {
					// Format for email compatibility
					$fraudTransactions[] = [
						'id' => $decoded['id'],
						'summary' => $decoded['summary'] ?? ''
					];
				}
			}
			return $fraudTransactions;
		} catch ( \Exception $ex ) {
			Logger::warning( 'Failed to retrieve fraud transaction data from Redis', [
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

			// Set redis "lockfile" with expiry time of alert suppression period
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
