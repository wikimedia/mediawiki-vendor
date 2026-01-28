<?php

namespace SmashPig\Tests\Logging;

use PHPUnit\Framework\TestCase;
use SmashPig\Core\Logging\ApiTimings;
use SmashPig\Core\Logging\TaggedLogger;

/**
 * @group Timings
 */
class ApiTimingsTest extends TestCase {

	public function testBuildTagLowerCaseAndFormatSegments(): void {
		$this->assertSame(
			'[adyen|cc|authorise|request|time]',
			ApiTimings::buildTag( 'Adyen', 'cc', 'Authorise' )
		);
	}

	public function testLogUsesInjectedLoggerAndFormatsMessage(): void {
		$testLogTag = '[adyen|cc|authorise|request|time]';
		$testRequestTimeElapsed = 1.23456789;

		$expectedMessage = $testLogTag . ' ' . number_format( $testRequestTimeElapsed, 6 ) . 's';
		$expectedContext = [
			'gateway_txn_id' => 'abc123'
		];

		$mockLogger = $this->createMock( TaggedLogger::class );
		$mockLogger->expects( $this->once() )
			->method( 'info' )
			->with( $expectedMessage, $expectedContext );

		ApiTimings::log(
			$testLogTag,
			$testRequestTimeElapsed,
			[
				'gateway_txn_id' => 'abc123'
			],
			$mockLogger
		);
	}

	public function testBuildTagThrowsInvalidArgExceptionWhenAFieldIsEmpty(): void {
		$this->expectException( \InvalidArgumentException::class );

		$tag = ApiTimings::buildTag( '', 'cc', 'authorise' );
	}
}
