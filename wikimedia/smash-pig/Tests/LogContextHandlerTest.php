<?php
namespace SmashPig\Tests;

use PHPUnit\Framework\TestCase;
use SmashPig\Core\Logging\LogContextHandler;
use SmashPig\Core\Logging\LogEvent;

/**
 * @group Logger
 */
class LogContextHandlerTest extends TestCase {
	public function testThreshold() {
		$eventToLog = new LogEvent(
			LOG_INFO, 'This should get to the stream'
		);
		$eventNotToLog = new LogEvent(
			LOG_DEBUG, 'This should not get to the stream'
		);
		$mockStream = $this->getMockForAbstractClass(
			'\SmashPig\Core\Logging\LogStreams\ILogStream'
		);
		$mockStream->expects( $this->once() )
			->method( 'processEvent' )
			->with( $eventToLog );
		$handler = new LogContextHandler(
			'blah',
			[
				$mockStream
			],
			LOG_INFO
		);
		$handler->addEventToContext( $eventToLog );
		$handler->addEventToContext( $eventNotToLog );
	}
}
