<?php

namespace SmashPig\PaymentProviders\Gravy\Tests\phpunit;

use PHPUnit\Framework\MockObject\MockObject;
use Predis\Client;
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
	 * @var MockObject|Client
	 */
	protected $mockRedisClient;

	public function setUp(): void {
		parent::setUp();

		// Create a mock Redis client
		$this->mockRedisClient = $this->createMock( Client::class );

		// Create a testable ErrorTracker that uses our mock
		// Note: inspired by Art of Unit Testing - Roy Osherove
		$this->errorTracker = new class( [
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

		$this->errorTracker->setMockClient( $this->mockRedisClient );
	}

	public function testTrackErrorAndCheckThreshold(): void {
		$response = [
			'id' => 'txn_' . time() . '_' . mt_rand(),
			'external_identifier' => 'donation_' . mt_rand(),
			'amount' => 1000,
			'currency' => 'USD',
			'payment_method' => [ 'method' => 'card' ],
			'status' => 'authorization_declined'
		];

		$error = ErrorHelper::buildTrackableErrorFromResponse( 'card_declined', 'payment', $response );

		// Mock the __call method for incr and expire
		$this->mockRedisClient->expects( $this->exactly( 2 ) )
			->method( '__call' )
			->willReturnCallback( static function ( $method, $args ) {
				if ( $method === 'incr' ) {
					return 1; // First call returns 1
				}
				if ( $method === 'expire' ) {
					return true;
				}
				return null;
			} );

		$result = $this->errorTracker->trackErrorAndCheckThreshold( $error );
		$this->assertTrue( $result ); // Should return true for successful error tracking
	}

	/**
	 * Test threshold breaching with 'cancelled_buyer_approval' error from ErrorCheckerTest
	 */
	public function testThresholdBreachWithCancelledBuyerApproval(): void {
		$uniqueCode = 'cancelled_buyer_approval';
		$response = [
			'id' => 'txn_' . time() . '_' . mt_rand(),
			'external_identifier' => 'donation_' . mt_rand(),
			'amount' => 1000,
			'currency' => 'USD',
			'payment_method' => [ 'method' => 'trustly' ],
			'error_code' => $uniqueCode,
			'status' => 'authorization_failed'
		];

		$error = ErrorHelper::buildTrackableErrorFromResponse( $uniqueCode, 'code', $response );

		// Mock Redis behavior for threshold breach scenario
		// Calls: incr (returns 20, threshold reached), exists (check alert), setex (set suppression)
		// Note: expire is NOT called when incr returns > 1
		$this->mockRedisClient->expects( $this->exactly( 3 ) )
			->method( '__call' )
			->willReturnCallback( static function ( $method, $args ) {
				if ( $method === 'incr' ) {
					return 20; // Threshold reached (20th occurrence)
				}
				if ( $method === 'exists' ) {
					return false; // No recent alert
				}
				if ( $method === 'setex' ) {
					return true; // Successfully set alert suppression
				}
				return null;
			} );

		// Should reach the threshold
		$result = $this->errorTracker->trackErrorAndCheckThreshold( $error );
		$this->assertTrue( $result, "Error tracking should exceed threshold at occurrence 20" );
	}

	public function testThresholdBreachFirstTimeTriggersAlert(): void {
		$uniqueCode = 'payment_declined_first_time';
		$response = [
			'id' => 'txn_' . time() . '_' . mt_rand(),
			'external_identifier' => 'donation_' . mt_rand(),
			'amount' => 1000,
			'currency' => 'USD',
			'payment_method' => [ 'method' => 'card' ],
			'error_code' => $uniqueCode,
			'status' => 'authorization_failed'
		];

		$error = ErrorHelper::buildTrackableErrorFromResponse( $uniqueCode, 'code', $response );

		// Mock Redis behavior - threshold just reached, no recent alert exists
		// Calls: incr (returns 20), exists (returns false - no recent alert), setex (set suppression)
		$this->mockRedisClient->expects( $this->exactly( 3 ) )
			->method( '__call' )
			->willReturnCallback( static function ( $method, $args ) {
				if ( $method === 'incr' ) {
					return 20; // Threshold reached (20th occurrence)
				}
				if ( $method === 'exists' ) {
					return false; // No recent alert exists
				}
				if ( $method === 'setex' ) {
					return true; // Successfully set alert suppression
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
		$uniqueCode = 'cancelled_buyer_approval_continued';
		$response = [
			'id' => 'txn_' . time() . '_' . mt_rand(),
			'external_identifier' => 'donation_' . mt_rand(),
			'amount' => 1000,
			'currency' => 'USD',
			'payment_method' => [ 'method' => 'trustly' ],
			'error_code' => $uniqueCode,
			'status' => 'authorization_failed'
		];

		$error = ErrorHelper::buildTrackableErrorFromResponse( $uniqueCode, 'code', $response );

		// Mock Redis behavior - return count above threshold, alert was recently sent
		// Calls: incr (returns 25), exists (returns true - alert recently sent)
		// Note: setex is NOT called when alert was recently sent
		$this->mockRedisClient->expects( $this->exactly( 2 ) )
			->method( '__call' )
			->willReturnCallback( static function ( $method, $args ) {
				if ( $method === 'incr' ) {
					return 25; // Above threshold
				}
				if ( $method === 'exists' ) {
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
		$uniqueCode = 'test_error';
		$response = [
			'id' => 'txn_123',
			'external_identifier' => 'donation_456',
			'amount' => 1000,
			'currency' => 'USD',
			'payment_method' => [ 'method' => 'card' ],
			'error_code' => $uniqueCode,
			'status' => 'authorization_failed'
		];

		$error = ErrorHelper::buildTrackableErrorFromResponse( $uniqueCode, 'code', $response );

		// Mock Redis throwing an exception
		$this->mockRedisClient->expects( $this->once() )
			->method( '__call' )
			->with( 'incr' )
			->willThrowException( new \Exception( 'Redis connection failed' ) );

		// Should return false when Redis fails
		$result = $this->errorTracker->trackErrorAndCheckThreshold( $error );
		$this->assertFalse( $result, "Should return false when Redis connection fails" );
	}

	/**
	 * Test that first occurrence sets expiration
	 */
	public function testFirstOccurrenceSetsExpiration(): void {
		$uniqueCode = 'test_error_first';
		$response = [
			'id' => 'txn_123',
			'external_identifier' => 'donation_456',
			'amount' => 1000,
			'currency' => 'USD',
			'payment_method' => [ 'method' => 'card' ],
			'error_code' => $uniqueCode,
			'status' => 'authorization_failed'
		];

		$error = ErrorHelper::buildTrackableErrorFromResponse( $uniqueCode, 'code', $response );

		// Mock Redis behavior - first call returns 1, then expire is called
		$this->mockRedisClient->expects( $this->exactly( 2 ) )
			->method( '__call' )
			->withConsecutive(
				[ 'incr', $this->anything() ],
				[ 'expire', $this->anything() ]
			)
			->willReturnOnConsecutiveCalls( 1, true );

		$result = $this->errorTracker->trackErrorAndCheckThreshold( $error );
		$this->assertTrue( $result );
	}

	/**
	 * Test that subsequent occurrences don't set expiration
	 */
	public function testSubsequentOccurrencesDontSetExpiration(): void {
		$uniqueCode = 'test_error_subsequent';
		$response = [
			'id' => 'txn_123',
			'external_identifier' => 'donation_456',
			'amount' => 1000,
			'currency' => 'USD',
			'payment_method' => [ 'method' => 'card' ],
			'error_code' => $uniqueCode,
			'status' => 'authorization_failed'
		];

		$error = ErrorHelper::buildTrackableErrorFromResponse( $uniqueCode, 'code', $response );

		// Mock Redis behavior - subsequent call returns 5 (no expire should be called)
		$this->mockRedisClient->expects( $this->once() )
			->method( '__call' )
			->with( 'incr' )
			->willReturn( 5 );

		$result = $this->errorTracker->trackErrorAndCheckThreshold( $error );
		$this->assertTrue( $result );
	}

	/**
	 * Test key generation format
	 */
	public function testKeyGenerationFormat(): void {
		$uniqueCode = 'test_error_123';
		$response = [
			'id' => 'txn_123',
			'external_identifier' => 'donation_456',
			'amount' => 1000,
			'currency' => 'USD',
			'payment_method' => [ 'method' => 'card' ],
			'error_code' => $uniqueCode,
			'status' => 'authorization_failed'
		];

		$error = ErrorHelper::buildTrackableErrorFromResponse( $uniqueCode, 'code', $response );

		$this->mockRedisClient->expects( $this->exactly( 2 ) )
			->method( '__call' )
			->withConsecutive(
				[ 'incr', $this->callback( static function ( $args ) {
					return (bool)preg_match( '/^gravy_error_threshold_test_error_123:\d+$/', $args[0] );
				} ) ],
				[ 'expire', $this->anything() ]
			)
			->willReturnOnConsecutiveCalls( 1, true );

		$result = $this->errorTracker->trackErrorAndCheckThreshold( $error );
		$this->assertTrue( $result );
	}

	public function testAlertSuppressionKeyGeneration(): void {
		$uniqueCode = 'test_error_with-special.chars@123';
		$response = [
			'id' => 'txn_' . time() . '_' . mt_rand(),
			'external_identifier' => 'donation_' . mt_rand(),
			'amount' => 1000,
			'currency' => 'USD',
			'payment_method' => [ 'method' => 'card' ],
			'error_code' => $uniqueCode,
			'status' => 'authorization_failed'
		];

		$error = ErrorHelper::buildTrackableErrorFromResponse( $uniqueCode, 'code', $response );

		// Mock Redis behavior for threshold breach with alert key generation
		// Calls: incr (returns 20), exists (check alert key), setex (set alert suppression)
		$this->mockRedisClient->expects( $this->exactly( 3 ) )
			->method( '__call' )
			->withConsecutive(
				[ 'incr', $this->callback( static function ( $args ) {
					// Validate the error key format: prefix + sanitized_code + time_slot
					return (bool)preg_match( '/^gravy_error_threshold_test_error_with_special_chars_123:\d+$/', $args[0] );
				} ) ],
				[ 'exists', $this->callback( static function ( $args ) {
					// Validate the alert key format: error_key + :alerted
					return (bool)preg_match( '/^gravy_error_threshold_test_error_with_special_chars_123:\d+:alerted$/', $args[0] );
				} ) ],
				[ 'setex', $this->callback( static function ( $args ) {
					// Verify setex uses the same alert key format
					return (bool)preg_match( '/^gravy_error_threshold_test_error_with_special_chars_123:\d+:alerted$/', $args[0] );
				} ) ]
			)
			->willReturnOnConsecutiveCalls( 20, false, true ); // threshold reached, no recent alert, suppression set

		$result = $this->errorTracker->trackErrorAndCheckThreshold( $error );
		$this->assertTrue( $result, "Should return true when threshold is exceeded and alert key is properly generated" );
	}

	public function testInvalidRedisKeyCharactersAreCleanedUp(): void {
		$invalidErrorCode = 'test error@$%';

		$response = [
			'id' => 'txn_123',
			'external_identifier' => 'donation_456',
			'amount' => 1000,
			'currency' => 'USD',
			'payment_method' => [ 'method' => 'card' ],
			'error_code' => $invalidErrorCode,
			'status' => 'authorization_failed'
		];

		$error = ErrorHelper::buildTrackableErrorFromResponse( $invalidErrorCode, 'code', $response );

		// Expect the Redis key to be sanitised - all non-alphanumeric characters should become underscores
		$expectedSanitizedKey = 'test_error___';

		$this->mockRedisClient->expects( $this->exactly( 2 ) )
			->method( '__call' )
			->withConsecutive(
				[ 'incr', $this->callback( static function ( $args ) use ( $expectedSanitizedKey ) {
					// Check that the key starts with the prefix and sanitized error code
					return (bool)preg_match( "/^gravy_error_threshold_{$expectedSanitizedKey}:\d+$/", $args[0] );
				} ) ],
				[ 'expire', $this->anything() ]
			)
			->willReturnOnConsecutiveCalls( 1, true );

		$result = $this->errorTracker->trackErrorAndCheckThreshold( $error );
		$this->assertTrue( $result, "Should successfully track error with sanitised key" );
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

		$nonIgnoredError = [
			'error_code' => 'card_declined',
			'error_type' => 'code',
			'gateway_txn_id' => 'txn_123',
			'external_identifier' => 'donation_456',
			'amount' => 1000,
			'currency' => 'USD',
			'payment_method' => 'card'
		];

		// Expect Redis calls for non-ignored error codes
		$this->mockRedisClient->expects( $this->exactly( 2 ) )
			->method( '__call' )
			->willReturnCallback( static function ( $method, $args ) {
				if ( $method === 'incr' ) {
					return 1; // First call returns 1
				}
				if ( $method === 'expire' ) {
					return true;
				}
				return null;
			} );

		$result = $errorTrackerWithIgnoreList->trackErrorAndCheckThreshold( $nonIgnoredError );
		$this->assertTrue( $result, "Should return true and track non-ignored error codes normally" );
	}
}
