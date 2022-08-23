<?php

namespace SmashPig\PaymentProviders\Adyen\Test;

use SmashPig\Core\Context;
use SmashPig\Core\Logging\Logger;
use SmashPig\PaymentProviders\Adyen\RestResponseValidator;
use SmashPig\Tests\BaseSmashPigUnitTestCase;

class RestResponseValidatorTest extends BaseSmashPigUnitTestCase {

	/** @var RestResponseValidator */
	protected $validator;
	protected $mockLogStream;

	public function setUp(): void {
		parent::setUp();
		$this->validator = new RestResponseValidator();
		$this->mockLogStream = $this->getMockForAbstractClass(
			'\SmashPig\Core\Logging\LogStreams\ILogStream'
		);
		$providerConfig = Context::get()->getProviderConfiguration();
		$providerConfig->overrideObjectInstance(
			'logging/log-streams/syslog', $this->mockLogStream
		);
		// Reinitialize the logger so it uses our mock stream and only logs
		// at level ERROR or higher.
		Logger::init( 'test', LOG_ERR, $providerConfig, 'restResponseTest' );
	}

	/**
	 * Should not log an error nor retry
	 */
	public function testStatus500WithErrorCode() {
		$this->mockLogStream->expects( $this->never() )
			->method( 'processEvent' );

		$result = $this->validator->shouldRetry( [
			'status' => 500,
			'body' =>
				'{"status":500,"errorCode":"905_1","message":' .
				'"Could not find an acquirer account for the provided txvariant (uatp), currency (NOK), and action (AUTH).",' .
				'"errorType":"configuration","pspReference":"SZ7VN2XQSCZ28222"}'
		] );

		$this->assertFalse( $result );
	}

	/**
	 * Should log an error and not retry
	 */
	public function testStatus500WithNoErrorCode() {
		$this->mockLogStream->expects( $this->once() )
			->method( 'processEvent' );
		$result = $this->validator->shouldRetry( [
			'status' => 500,
			'body' => '{}'
		] );
		$this->assertFalse( $result );
	}
}
