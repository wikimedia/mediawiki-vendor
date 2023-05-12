<?php

namespace SmashPig\PaymentProviders\dlocal\Tests;

use SmashPig\Core\Context;
use SmashPig\PaymentProviders\dlocal\RestResponseValidator;
use SmashPig\Tests\BaseSmashPigUnitTestCase;
use SmashPig\Tests\TestingProviderConfiguration;
use Symfony\Component\HttpFoundation\Response;

class RestResponseValidatorTest extends BaseSmashPigUnitTestCase {
	/**
	 * @var \PHPUnit_Framework_MockObject_MockObject|\SmashPig\Core\Logging\LogStreams\SyslogLogStream
	 */
	protected $mockLogStream;

	/**
	 * @var RestResponseValidator
	 */
	protected $validator;

	public function setUp(): void {
		parent::setUp();
		$providerConfig = TestingProviderConfiguration::createForProvider(
			'dlocal', Context::get()->getGlobalConfiguration()
		);
		$this->mockLogStream = $this->createMock( 'SmashPig\Core\Logging\LogStreams\SyslogLogStream' );
		$providerConfig->overrideObjectInstance( 'logging/log-streams/syslog', $this->mockLogStream );
		// Need to do this AFTER overriding the log stream because setProviderConfiguration will
		// initialize a new logger object using the current contents of the log-streams
		Context::get()->setProviderConfiguration( $providerConfig );
		$this->validator = new RestResponseValidator();
	}

	public function test400ErrorWithParamDoesNotLogError() {
		$this->mockLogStream->expects( $this->never() )
			->method( 'processEvent' );
		$result = $this->validator->shouldRetry( [
			'status' => Response::HTTP_BAD_REQUEST,
			'body' => '{"code":5001,"message":"Invalid parameter: payer.document","param":"payer.document"}'
		] );
		$this->assertFalse( $result );
	}

	public function test400ErrorWithNoParamDoesLogError() {
		$this->mockLogStream->expects( $this->once() )
			->method( 'processEvent' )
			->with( $this->callback( function ( $logEvent ) {
				$this->assertEquals( LOG_ERR, $logEvent->level );
				return true;
			} ) );
		$result = $this->validator->shouldRetry( [
			'status' => Response::HTTP_BAD_REQUEST,
			'body' => '{"code":5000,"message":"Invalid request."}'
		] );
		$this->assertFalse( $result );
	}
}
