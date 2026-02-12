<?php

namespace SmashPig\PaymentProviders\Gravy\Tests\phpunit;

use SmashPig\Core\Logging\ApiOperation;
use SmashPig\Core\Logging\ApiOperationAttribute;
use SmashPig\Core\Logging\TaggedLogger;
use SmashPig\PaymentProviders\Gravy\GravyApiTimingTrait;
use SmashPig\Tests\BaseSmashPigUnitTestCase;

/**
 * @group Gravy
 * @group Timings
 */
class GravyApiTimingTraitTest extends BaseSmashPigUnitTestCase {

	public function testTimedCallInvokesCallableAndReturnsResult(): void {
		$testDouble = $this->createTimingTraitTestDouble();

		$result = $testDouble->authorize( static fn () => [ 'id' => 'txn-123' ] );

		$this->assertSame( [ 'id' => 'txn-123' ], $result );
	}

	public function testTimedCallLogsTagWithBackendProcessor(): void {
		$mockLogger = $this->createMock( TaggedLogger::class );

		// Response includes payment_service.payment_service_definition_id
		$response = [
			'id' => 'txn-123',
			'payment_service' => [
				'payment_service_definition_id' => 'adyen-card'
			]
		];

		// Expect tag format: [gravy|adyen|cc|authorize|request|time]
		$mockLogger->expects( $this->once() )
			->method( 'info' )
			->with(
				$this->stringContains( '[gravy|adyen|cc|authorize|request|time]' ),
				$this->anything()
			);

		$testDouble = $this->createTimingTraitTestDouble();
		$testDouble->authorize( static fn () => $response, $mockLogger );
	}

	public function testTimedCallLogsEmptyBackendWhenNoPaymentService(): void {
		$mockLogger = $this->createMock( TaggedLogger::class );

		// Response without payment_service (e.g., report or session calls)
		$response = [
			'id' => 'report-123',
			'status' => 'completed'
		];

		// Expect tag format with empty backend: [gravy||cc|authorize|request|time]
		$mockLogger->expects( $this->once() )
			->method( 'info' )
			->with(
				$this->stringContains( '[gravy||cc|authorize|request|time]' ),
				$this->anything()
			);

		$testDouble = $this->createTimingTraitTestDouble();
		$testDouble->authorize( static fn () => $response, $mockLogger );
	}

	public function testTimedCallExtractsProcessorFromHyphenatedDefinitionId(): void {
		$mockLogger = $this->createMock( TaggedLogger::class );

		$response = [
			'payment_service' => [
				'payment_service_definition_id' => 'braintree-paypal'
			]
		];

		// Should extract 'braintree' from 'braintree-paypal'
		$mockLogger->expects( $this->once() )
			->method( 'info' )
			->with(
				$this->stringContains( '[gravy|braintree|cc|authorize|request|time]' ),
				$this->anything()
			);

		$testDouble = $this->createTimingTraitTestDouble();
		$testDouble->authorize( static fn () => $response, $mockLogger );
	}

	public function testTimedCallHandlesNullResponse(): void {
		$mockLogger = $this->createMock( TaggedLogger::class );

		// Expect empty backend segment when response is null
		$mockLogger->expects( $this->once() )
			->method( 'info' )
			->with(
				$this->stringContains( '[gravy||cc|authorize|request|time]' ),
				$this->anything()
			);

		$testDouble = $this->createTimingTraitTestDouble();
		$testDouble->authorize( static fn () => null, $mockLogger );
	}

	public function testTimedCallThrowsWhenApiOperationAttributeMissing(): void {
		$testDouble = $this->createTimingTraitTestDouble();

		$this->expectException( \UnexpectedValueException::class );
		$this->expectExceptionMessage( 'is missing the #[ApiOperationAttribute] attribute' );

		$testDouble->methodWithoutAttribute( static fn () => 'result' );
	}

	public function testTimedCallUsesCanonicalOperationName(): void {
		$mockLogger = $this->createMock( TaggedLogger::class );

		$response = [
			'payment_service' => [
				'payment_service_definition_id' => 'stripe-card'
			]
		];

		// Should use 'capture' (from attribute) not 'approvePayment' (method name)
		$mockLogger->expects( $this->once() )
			->method( 'info' )
			->with(
				$this->stringContains( '[gravy|stripe|cc|capture|request|time]' ),
				$this->anything()
			);

		$testDouble = $this->createTimingTraitTestDouble();
		$testDouble->capture( static fn () => $response, $mockLogger );
	}

	/**
	 * Creates a test double class that uses GravyApiTimingTrait with
	 * multiple API operations for testing different scenarios.
	 */
	private function createTimingTraitTestDouble(): object {
		return new class {
			use GravyApiTimingTrait;

			protected function getPaymentMethodForTimings(): string {
				return 'cc';
			}

			#[ApiOperationAttribute( ApiOperation::AUTHORIZE )]
			public function authorize( callable $fn, ?TaggedLogger $logger = null ) {
				return $this->timedCall( __FUNCTION__, $fn, [], $logger );
			}

			#[ApiOperationAttribute( ApiOperation::CAPTURE )]
			public function capture( callable $fn, ?TaggedLogger $logger = null ) {
				return $this->timedCall( __FUNCTION__, $fn, [], $logger );
			}

			#[ApiOperationAttribute( ApiOperation::REFUND )]
			public function refund( callable $fn, ?TaggedLogger $logger = null ) {
				return $this->timedCall( __FUNCTION__, $fn, [], $logger );
			}

			/** Deliberately missing #[ApiOperationAttribute] for testing */
			public function methodWithoutAttribute( callable $fn, ?TaggedLogger $logger = null ) {
				return $this->timedCall( __FUNCTION__, $fn, [], $logger );
			}
		};
	}
}
