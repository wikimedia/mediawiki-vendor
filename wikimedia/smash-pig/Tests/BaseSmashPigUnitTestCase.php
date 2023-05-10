<?php
namespace SmashPig\Tests;

use PHPUnit\Framework\TestCase;
use PHPUnit_Framework_MockObject_MockObject;
use SmashPig\Core\Context;
use SmashPig\Core\Http\CurlWrapper;

class BaseSmashPigUnitTestCase extends TestCase {
	/**
	 * @var PHPUnit_Framework_MockObject_MockObject
	 */
	protected $curlWrapper;

	public function setUp() : void {
		parent::setUp();
		$globalConfig = TestingGlobalConfiguration::create();
		TestingContext::init( $globalConfig );
		$this->curlWrapper = $this->createMock( '\SmashPig\Core\Http\CurlWrapper' );
	}

	public function tearDown() : void {
		TestingDatabase::clearStatics();
		Context::set(); // Nullify the context for next run.
	}

	/**
	 * @param string $filepath Full path to file representing a
	 *  response (headers, blank line, body), which must use dos-style
	 *  \r\n line endings.
	 * @param int $statusCode
	 */
	protected function setUpResponse( $filepath, $statusCode ) {
		$parsed = $this->getParsedCurlWrapperResponse( $filepath, $statusCode );
		$this->curlWrapper->method( 'execute' )->willReturn( $parsed );
	}

	protected function getParsedCurlWrapperResponse( $filepath, $statusCode ) {
		$contents = file_get_contents( $filepath );
		$header_size = strpos( $contents, "\r\n\r\n" ) + 4;
		return CurlWrapper::parseResponse(
			$contents, [ 'http_code' => $statusCode, 'header_size' => $header_size ]
		);
	}

	protected function loadJson( $path ) {
		return json_decode( file_get_contents( $path ), true );
	}

	/**
	 * @param string $provider
	 * @return TestingProviderConfiguration
	 */
	protected function setProviderConfiguration( string $provider ) {
		$ctx = Context::get();
		$globalConfig = $ctx->getGlobalConfiguration();
		$config = TestingProviderConfiguration::createForProvider( $provider, $globalConfig );
		$ctx->setProviderConfiguration( $config );
		$config->overrideObjectInstance( 'curl/wrapper', $this->curlWrapper );
		return $config;
	}
}
