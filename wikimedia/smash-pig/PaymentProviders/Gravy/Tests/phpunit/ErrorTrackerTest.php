<?php

namespace SmashPig\PaymentProviders\Gravy\Tests\phpunit;

use PHPUnit\Framework\MockObject\MockObject;
use Predis\Client;
use SmashPig\PaymentProviders\Gravy\Errors\ErrorChecker;
use SmashPig\PaymentProviders\Gravy\Errors\ErrorHelper;
use SmashPig\PaymentProviders\Gravy\Errors\ErrorTracker;
use SmashPig\PaymentProviders\Gravy\Tests\BaseGravyTestCase;

/**
 * @group Gravy
 */
class ErrorTrackerTest extends BaseGravyTestCase {

	/**
	 * @var ErrorTracker
	 */
	protected ErrorTracker $errorTracker;

	/**
	 * @var Client|MockObject
	 */
	protected MockObject|Client $mockRedisClient;

	public function setUp(): void {
		parent::setUp();
		$this->mockRedisClient = $this->createMock( Client::class );
		$this->errorTracker = $this->getTestableErrorTracker();
		$this->errorTracker->setMockClient( $this->mockRedisClient );
	}

	public function testTrackErrorAndCheckThreshold(): void {
		$testTransactionId = '4cf23c6b-1a2d-4f5e-9d8b-e7f6c4a3b2d1';
		$testErrorCode = 'invalid_payment_method';
		$testResponse = $this->getTestErrorResponse();
		$testResponse['id'] = $testTransactionId;
		$testResponse['error_code'] = $testErrorCode;
		$testErrorDetails = ( new ErrorChecker() )->getResponseErrorDetails( $testResponse );

		$error = ErrorHelper::buildTrackableError(
			$testErrorDetails['error_code'],
			$testErrorDetails['error_type'],
			$testResponse
		);

		// The Redis client uses magic method __call to dynamically handle Redis commands.
		// We mock __call instead of individual methods because the Redis client doesn't
		// actually have concrete methods for commands like 'sadd', 'scard', etc.
		// These commands are intercepted by __call and forwarded to Redis.
		// Calls: sadd (returns 1, new item), scard (returns 1, below threshold), expire (set redis key TTL for first occurrence)
		$this->mockRedisClient->expects( $this->exactly( 3 ) )
			->method( '__call' )
			->willReturnCallback( function ( $method, $args ) use ( $testTransactionId ) {
				if ( $method === 'sadd' ) {
					$expectedSetKeyPattern = '^gravy_error_threshold_invalid_payment_method:\d+';
					$expectedValue = '{"id":"' . $testTransactionId . '","summary":" - Adyen, 206065365.1, EUR 1500.00, via card, from FR"}';
					$this->assertTrue( (bool)preg_match( "/$expectedSetKeyPattern/", $args[0] ) );
					$this->assertSame( [ $expectedValue ], $args[1] ); // sadd expects an array of values
					return 1;
				}
				if ( $method === 'scard' ) {
					return 1; // simulate set reporting it has 1 item
				}
				if ( $method === 'expire' ) {
					return true; // expire should be called for the first record
				}
				return null;
			} );

		// Track the error
		$result = $this->errorTracker->trackErrorAndCheckThreshold( $error );
		// Should return true for successful error tracking
		$this->assertTrue( $result );
	}

	/**
	 * Test that first occurrence sets expiration
	 */
	public function testFirstOccurrenceSetsExpiration(): void {
		$testResponse = $this->getTestErrorResponse();
		$testResponse['error_code'] = 'test_error_first';
		$testErrorDetails = ( new ErrorChecker() )->getResponseErrorDetails( $testResponse );

		$error = ErrorHelper::buildTrackableError(
			$testErrorDetails['error_code'],
			$testErrorDetails['error_type'],
			$testResponse
		);

		// The Redis client uses magic method __call to dynamically handle Redis commands.
		// We mock __call instead of individual methods because the Redis client doesn't
		// actually have concrete methods for commands like 'sadd', 'scard', etc.
		// These commands are intercepted by __call and forwarded to Redis.
		// Calls: sadd (returns 1, new item), scard (returns 1, first occurrence), expire (set redis key TTL)
		$this->mockRedisClient->expects( $this->exactly( 3 ) )
			->method( '__call' )
			->withConsecutive(
				[ 'sadd', $this->anything() ],
				[ 'scard', $this->anything() ],
				[ 'expire', $this->anything() ]
			)
			->willReturnOnConsecutiveCalls( 1, 1, true );

		$result = $this->errorTracker->trackErrorAndCheckThreshold( $error );
		$this->assertTrue( $result );
	}

	/**
	 * Test that subsequent occurrences don't set expiration
	 */
	public function testSubsequentOccurrencesDontSetExpiration(): void {
		$testResponse = $this->getTestErrorResponse();
		$testResponse['error_code'] = 'test_error_subsequent';
		$testErrorDetails = ( new ErrorChecker() )->getResponseErrorDetails( $testResponse );

		$error = ErrorHelper::buildTrackableError(
			$testErrorDetails['error_code'],
			$testErrorDetails['error_type'],
			$testResponse
		);

		// The Redis client uses magic method __call to dynamically handle Redis commands.
		// We mock __call instead of individual methods because the Redis client doesn't
		// actually have concrete methods for commands like 'sadd', 'scard', etc.
		// These commands are intercepted by __call and forwarded to Redis.
		// Calls: sadd (returns 0, already exists), scard (returns 5, subsequent occurrence - no expire called)
		$this->mockRedisClient->expects( $this->exactly( 2 ) )
			->method( '__call' )
			->withConsecutive(
				[ 'sadd', $this->anything() ],
				[ 'scard', $this->anything() ]
			)
			->willReturnOnConsecutiveCalls( 0, 5 ); // Already exists, set has 5 items

		$result = $this->errorTracker->trackErrorAndCheckThreshold( $error );
		$this->assertTrue( $result );
	}

	public function testThresholdBreachFirstTimeTriggersAlert(): void {
		$testResponse = $this->getTestErrorResponse();
		$testResponse['error_code'] = 'payment_declined_first_time';
		$testErrorDetails = ( new ErrorChecker() )->getResponseErrorDetails( $testResponse );

		$error = ErrorHelper::buildTrackableError(
			$testErrorDetails['error_code'],
			$testErrorDetails['error_type'],
			$testResponse
		);

		// The Redis client uses magic method __call to dynamically handle Redis commands.
		// We mock __call instead of individual methods because the Redis client doesn't
		// actually have concrete methods for commands like 'sadd', 'scard', etc.
		// These commands are intercepted by __call and forwarded to Redis.
		// Calls: sadd (returns 1, new item), scard (returns 20, threshold reached), exists (check alert), setex (set suppression)
		$this->mockRedisClient->expects( $this->exactly( 4 ) )
			->method( '__call' )
			->willReturnCallback( static function ( $method, $args ) {
				if ( $method === 'sadd' ) {
					return 1; // simulate response for adding a new item to the set
				}
				if ( $method === 'scard' ) {
					return 20; // simulate set reporting it has 20 items - the threshold is then reached
				}
				if ( $method === 'exists' ) {
					return false; // simulate response to indicate no recent alerts have been sent
				}
				if ( $method === 'setex' ) {
					// a call to 'setex' tells us that ErrorTracker::markAlertAsSent() is called which runs
					// after the alert is sent. now verify alert suppression key matches pattern with :alerted suffix
					$actualKey = $args[0] ?? '';
					$expectedKeyPattern = "^gravy_error_threshold_payment_declined_first_time:\d+:alerted$";
					return (bool)preg_match( "/$expectedKeyPattern/", $actualKey );
				}
				return null;
			} );

		// Should trigger alert and return true when threshold is reached for first time
		$result = $this->errorTracker->trackErrorAndCheckThreshold( $error );
		$this->assertTrue( $result, "Should return true when threshold is exceeded and alert is triggered" );
	}

	/**
	 * Test that the threshold breach alert is suppressed when an alert was recently sent.
	 *
	 * @return void
	 */
	public function testThresholdBreachDuplicateAlertIsSuppressed(): void {
		$testResponse = $this->getTestErrorResponse();
		$testResponse['error_code'] = 'cancelled_buyer_approval_continued';
		$testErrorDetails = ( new ErrorChecker() )->getResponseErrorDetails( $testResponse );

		$error = ErrorHelper::buildTrackableError(
			$testErrorDetails['error_code'],
			$testErrorDetails['error_type'],
			$testResponse
		);

		// The Redis client uses magic method __call to dynamically handle Redis commands.
		// We mock __call instead of individual methods because the Redis client doesn't
		// actually have concrete methods for commands like 'sadd', 'scard', etc.
		// These commands are intercepted by __call and forwarded to Redis.
		// Calls: sadd (returns 1, new item), scard (returns 25, above threshold), exists (returns true, alert recently sent)
		$this->mockRedisClient->expects( $this->exactly( 3 ) )
			->method( '__call' )
			->willReturnCallback( static function ( $method, $args ) {
				if ( $method === 'sadd' ) {
					return 1; // New item added
				}
				if ( $method === 'scard' ) {
					return 25; // Above threshold
				}
				if ( $method === 'exists' ) {
					// A call to 'exists' tells us that ErrorTracker::alertRecentlySent() is called
					return true; // Alert recently sent, so no new alert
				}
				return null;
			} );

		// Should continue to return true even if alert suppressed
		$result = $this->errorTracker->trackErrorAndCheckThreshold( $error );
		$this->assertTrue( $result, "Should continue to return true even if alert suppressed" );
	}

	/**
	 * Test Redis connection failure is handled gracefully
	 */
	public function testRedisConnectionFailureIsHandledGracefully(): void {
		$testResponse = $this->getTestErrorResponse();
		$testResponse['error_code'] = 'test_error';
		$testErrorDetails = ( new ErrorChecker() )->getResponseErrorDetails( $testResponse );

		$error = ErrorHelper::buildTrackableError(
			$testErrorDetails['error_code'],
			$testErrorDetails['error_type'],
			$testResponse
		);

		// The Redis client uses magic method __call to dynamically handle Redis commands.
		// We mock __call instead of individual methods because the Redis client doesn't
		// actually have concrete methods for commands like 'sadd', 'scard', etc.
		// These commands are intercepted by __call and forwarded to Redis.
		// Calls: sadd (throws exception to simulate Redis connection failure)
		$this->mockRedisClient->expects( $this->once() )
			->method( '__call' )
			->with( 'sadd' )
			->willThrowException( new \Exception( 'Redis connection failed' ) );

		// Should return false when Redis fails
		$result = $this->errorTracker->trackErrorAndCheckThreshold( $error );
		$this->assertFalse( $result, "Should return false when Redis connection fails" );
	}

	/**
	 * Test key generation format
	 */
	public function testKeyGenerationFormat(): void {
		$testResponse = $this->getTestErrorResponse();
		$testResponse['error_code'] = 'test_error_123';
		$testErrorDetails = ( new ErrorChecker() )->getResponseErrorDetails( $testResponse );

		$error = ErrorHelper::buildTrackableError(
			$testErrorDetails['error_code'],
			$testErrorDetails['error_type'],
			$testResponse
		);

		// The Redis client uses magic method __call to dynamically handle Redis commands.
		// We mock __call instead of individual methods because the Redis client doesn't
		// actually have concrete methods for commands like 'sadd', 'scard', etc.
		// These commands are intercepted by __call and forwarded to Redis.
		// Calls: sadd (returns 1, new item), scard (returns 1, first occurrence), expire (set redis key TTL)
		$this->mockRedisClient->expects( $this->exactly( 3 ) )
			->method( '__call' )
			->withConsecutive(
				[ 'sadd', $this->callback( static function ( $args ) {
					return (bool)preg_match( '/^gravy_error_threshold_test_error_123:\d+$/', $args[0] );
				} ) ],
				[ 'scard', $this->anything() ],
				[ 'expire', $this->anything() ]
			)
			->willReturnOnConsecutiveCalls( 1, 1, true );

		$result = $this->errorTracker->trackErrorAndCheckThreshold( $error );
		$this->assertTrue( $result );
	}

	public function testInvalidRedisKeyCharactersAreCleanedUp(): void {
		$testResponse = $this->getTestErrorResponse();
		$testResponse['error_code'] = 'test error@$%';
		$testErrorDetails = ( new ErrorChecker() )->getResponseErrorDetails( $testResponse );

		$error = ErrorHelper::buildTrackableError(
			$testErrorDetails['error_code'],
			$testErrorDetails['error_type'],
			$testResponse
		);

		// Expect the Redis key to be sanitised - all non-alphanumeric characters should become underscores
		$expectedSanitizedKey = 'test_error___';

		// The Redis client uses magic method __call to dynamically handle Redis commands.
		// We mock __call instead of individual methods because the Redis client doesn't
		// actually have concrete methods for commands like 'sadd', 'scard', etc.
		// These commands are intercepted by __call and forwarded to Redis.
		// Calls: sadd (returns 1, new item), scard (returns 1, first occurrence), expire (returns true, set redis key TTL)
		$this->mockRedisClient->expects( $this->exactly( 3 ) )
			->method( '__call' )
			->withConsecutive(
				[ 'sadd', $this->callback( static function ( $args ) use ( $expectedSanitizedKey ) {
					// Check that the key starts with the prefix and sanitized error code
					return (bool)preg_match( "/^gravy_error_threshold_{$expectedSanitizedKey}:\d+$/", $args[0] );
				} ) ],
				[ 'scard', $this->anything() ],
				[ 'expire', $this->anything() ]
			)
			->willReturnOnConsecutiveCalls( 1, 1, true );

		$result = $this->errorTracker->trackErrorAndCheckThreshold( $error );
		$this->assertTrue( $result, "Should successfully track error with sanitised key" );
	}

	public function testAlertSuppressionKeyGeneration(): void {
		$testResponse = $this->getTestErrorResponse();
		$testResponse['error_code'] = 'test_error_with-special.chars@123';
		$testErrorDetails = ( new ErrorChecker() )->getResponseErrorDetails( $testResponse );

		$error = ErrorHelper::buildTrackableError(
			$testErrorDetails['error_code'],
			$testErrorDetails['error_type'],
			$testResponse
		);

		// The Redis client uses magic method __call to dynamically handle Redis commands.
		// We mock __call instead of individual methods because the Redis client doesn't
		// actually have concrete methods for commands like 'sadd', 'scard', etc.
		// These commands are intercepted by __call and forwarded to Redis.
		// Calls: sadd (returns 1, new item), scard (returns 20, threshold reached), exists (returns false, no recent alert), setex (returns true, set alert suppression)
		$this->mockRedisClient->expects( $this->exactly( 4 ) )
			->method( '__call' )
			->withConsecutive(
				[ 'sadd', $this->callback( static function ( $args ) {
					// Validate the error key format: prefix + sanitized_code + time_slot
					return (bool)preg_match( '/^gravy_error_threshold_test_error_with_special_chars_123:\d+$/', $args[0] );
				} ) ],
				[ 'scard', $this->anything() ],
				[ 'exists', $this->callback( static function ( $args ) {
					// Validate the alert key format: error_key + :alerted
					return (bool)preg_match( '/^gravy_error_threshold_test_error_with_special_chars_123:\d+:alerted$/', $args[0] );
				} ) ],
				[ 'setex', $this->callback( static function ( $args ) {
					// Verify setex uses the same alert key format
					return (bool)preg_match( '/^gravy_error_threshold_test_error_with_special_chars_123:\d+:alerted$/', $args[0] );
				} ) ]
			)
			->willReturnOnConsecutiveCalls( 1, 20, false, true ); // new item, threshold reached, no recent alert, suppression set

		$result = $this->errorTracker->trackErrorAndCheckThreshold( $error );
		$this->assertTrue( $result, "Should return true when threshold is exceeded and alert key is properly generated" );
	}

	/**
	 * Test that nothing happens when enabled is false
	 */
	public function testNothingHappensWhenDisabled(): void {
		// Create a disabled ErrorTracker
		$disabledErrorTracker = new class( [
			'enabled' => false,
			'threshold' => 20,
			'time_window' => 1800,
			'key_prefix' => 'gravy_error_threshold_',
			'key_expiry_period' => 2400,
			'alert_suppression_period' => 120
		] ) extends ErrorTracker {
			private Client $mockClient;

			public function setMockClient( $client ): void {
				$this->mockClient = $client;
			}

			protected function createRedisClient(): Client {
				return $this->mockClient ?? parent::createRedisClient();
			}
		};

		$disabledErrorTracker->setMockClient( $this->mockRedisClient );

		$error = [
			'error_code' => 'test_error_disabled',
			'error_type' => 'code',
			'gateway_txn_id' => 'txn_123',
			'external_identifier' => 'donation_456',
			'amount' => 1000,
			'currency' => 'USD',
			'payment_method' => 'card'
		];

		// Expect no Redis calls when disabled
		$this->mockRedisClient->expects( $this->never() )
			->method( '__call' );

		$result = $disabledErrorTracker->trackErrorAndCheckThreshold( $error );
		$this->assertFalse( $result, "Should return false and do nothing when disabled" );
	}

	/**
	 * Test that error codes in the ignore list are not tracked
	 */
	public function testIgnoredErrorCodesAreNotTracked(): void {
		// Create an ErrorTracker with ignore list containing 'incomplete_buyer_approval'
		$errorTrackerWithIgnoreList = new class( [
			'enabled' => true,
			'threshold' => 20,
			'time_window' => 1800,
			'key_prefix' => 'gravy_error_threshold_',
			'key_expiry_period' => 2400,
			'alert_suppression_period' => 120,
			'ignore_list' => [ 'incomplete_buyer_approval' ]
		] ) extends ErrorTracker {
			private Client $mockClient;

			public function setMockClient( $client ): void {
				$this->mockClient = $client;
			}

			protected function createRedisClient(): Client {
				return $this->mockClient ?? parent::createRedisClient();
			}
		};

		$errorTrackerWithIgnoreList->setMockClient( $this->mockRedisClient );

		$ignoredError = [
			'error_code' => 'incomplete_buyer_approval',
			'error_type' => 'code',
			'gateway_txn_id' => 'txn_123',
			'external_identifier' => 'donation_456',
			'amount' => 1000,
			'currency' => 'USD',
			'payment_method' => 'card'
		];

		// Expect no Redis calls when error code is ignored
		$this->mockRedisClient->expects( $this->never() )
			->method( '__call' );

		$result = $errorTrackerWithIgnoreList->trackErrorAndCheckThreshold( $ignoredError );
		$this->assertFalse( $result, "Should return false and skip tracking for ignored error codes" );
	}

	/**
	 * Test that non-ignored error codes are still tracked normally when ignore list is present
	 */
	public function testNonIgnoredErrorCodesAreStillTracked(): void {
		// Create an ErrorTracker with ignore list containing 'incomplete_buyer_approval'
		$errorTrackerWithIgnoreList = new class( [
			'enabled' => true,
			'threshold' => 20,
			'time_window' => 1800,
			'key_prefix' => 'gravy_error_threshold_',
			'key_expiry_period' => 2400,
			'alert_suppression_period' => 120,
			'ignore_list' => [ 'incomplete_buyer_approval' ]
		] ) extends ErrorTracker {
			private Client $mockClient;

			public function setMockClient( $client ): void {
				$this->mockClient = $client;
			}

			protected function createRedisClient(): Client {
				return $this->mockClient ?? parent::createRedisClient();
			}
		};

		$errorTrackerWithIgnoreList->setMockClient( $this->mockRedisClient );

		// Create a proper response array and use ErrorHelper like other tests
		$response = [
			'id' => 'txn_123',
			'external_identifier' => 'donation_456',
			'amount' => 1000,
			'currency' => 'USD',
			'payment_method' => [ 'method' => 'card' ],
			'error_code' => 'card_declined',
			'status' => 'authorization_failed'
		];

		$nonIgnoredError = ErrorHelper::buildTrackableError( 'card_declined', 'code', $response );

		// The Redis client uses magic method __call to dynamically handle Redis commands.
		// We mock __call instead of individual methods because the Redis client doesn't
		// actually have concrete methods for commands like 'sadd', 'scard', etc.
		// These commands are intercepted by __call and forwarded to Redis.
		// Calls: sadd (returns 1, new item), scard (returns 1, first occurrence), expire (set redis key TTL)
		$this->mockRedisClient->expects( $this->exactly( 3 ) )
			->method( '__call' )
			->willReturnCallback( static function ( $method, $args ) {
				if ( $method === 'sadd' ) {
					return 1; // New item added
				}
				if ( $method === 'scard' ) {
					return 1; // Set has 1 item
				}
				if ( $method === 'expire' ) {
					return true;
				}
				return null;
			} );

		$result = $errorTrackerWithIgnoreList->trackErrorAndCheckThreshold( $nonIgnoredError );
		$this->assertTrue( $result, "Should return true and track non-ignored error codes normally" );
	}

	/**
	 * Get an 'Extract and override' testable Error Tracker
	 *
	 * @return ErrorTracker
	 */
	protected function getTestableErrorTracker(): ErrorTracker {
		return new class( [
			'enabled' => true,
			'threshold' => 20,
			'time_window' => 1800,
			'key_prefix' => 'gravy_error_threshold_',
			'key_expiry_period' => 2400,
			'alert_suppression_period' => 120
		] ) extends ErrorTracker {
			private Client $mockClient;

			public function setMockClient( $client ): void {
				$this->mockClient = $client;
			}

			protected function createRedisClient(): Client {
				return $this->mockClient ?? parent::createRedisClient();
			}
		};
	}

	/**
	 * @return array
	 */
	protected function getTestErrorResponse(): array {
		return [
			"type" => "transaction",
			"id" => "5cf23c6b-1a2d-4f5e-9d8b-e7f6c4a3b2d1",
			"reconciliation_id" => "7v7gQ2jlL2YtpiEm8xIW6T",
			"merchant_account_id" => "default",
			"currency" => "EUR",
			"amount" => 1500,
			"status" => "authorization_declined",
			"country" => "FR",
			"external_identifier" => "206065365.1",
			"intent" => "authorize",
			"method" => "card",
			"instrument_type" => "pan",
			"error_code" => "insufficient_funds",
			"payment_service" => [
				"payment_service_definition_id" => "adyen-card",
				"method" => "card",
				"display_name" => "Adyen"
			],
			"raw_response_description" => "51 : Insufficient funds/over credit limit",
			"intent_outcome" => "failed"
		];
	}
}
