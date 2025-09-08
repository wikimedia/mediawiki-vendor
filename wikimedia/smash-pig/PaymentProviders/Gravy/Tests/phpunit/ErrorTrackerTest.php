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
			'threshold' => 10,
			'time_window' => 3600,
			'key_prefix' => 'gravy_error_threshold_',
			'key_expiry_period' => 1800
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

		// Mock Redis behavior - simulate reaching threshold
		$this->mockRedisClient->expects( $this->once() )
			->method( '__call' )
			->with( 'incr' )
			->willReturn( 10 );

		// Should reach the threshold
		$result = $this->errorTracker->trackErrorAndCheckThreshold( $error );
		$this->assertTrue( $result, "Error tracking should exceed threshold at occurrence 10" );
	}

	/**
	 * Test threshold breaching continues to return true after being exceeded
	 */
	public function testThresholdBreachContinuesAfterExceeded(): void {
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

		// Mock Redis behavior - return count above threshold
		$this->mockRedisClient->expects( $this->once() )
			->method( '__call' )
			->with( 'incr' )
			->willReturn( 15 );

		// Should continue to return true after threshold breach
		$result = $this->errorTracker->trackErrorAndCheckThreshold( $error );
		$this->assertTrue( $result, "Should continue returning true after threshold breach" );
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

	/**
	 * Test that nothing happens when enabled is false
	 */
	public function testNothingHappensWhenDisabled(): void {
		// Create a disabled ErrorTracker
		$disabledErrorTracker = new class( [
			'enabled' => false,
			'threshold' => 10,
			'time_window' => 3600,
			'key_prefix' => 'gravy_error_threshold_',
			'key_expiry_period' => 1800
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
}
