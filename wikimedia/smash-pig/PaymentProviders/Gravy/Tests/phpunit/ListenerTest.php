<?php

namespace SmashPig\PaymentProviders\Gravy\Tests\phpunit;

use SmashPig\Core\Http\Request;
use SmashPig\PaymentProviders\Gravy\GravyListener;
use SmashPig\PaymentProviders\Gravy\Tests\BaseGravyTestCase;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ServerBag;

/**
 * @group Gravy
 * @covers \SmashPig\PaymentProviders\Gravy\Validators\ListenerValidator
 */
class ListenerTest extends BaseGravyTestCase {

	private GravyListener $listener;

	public function setUp(): void {
		parent::setUp();
		$this->listener = new GravyListener();
	}

	public function testHandleAuthenticationPending(): void {
		$expectedAuth = 'Basic ' . base64_encode( 'WikimediaFoundationTest:FoundationTest' );
		$request = $this->getMockRequest( 'authentication-pending-ipn.json' );
		$response = new Response();
		$this->listener->execute( $request, $response );
		$this->assertEquals( 200, $response->getStatusCode() );
	}

	protected function getMockRequest( string $requestFile ) {
		$request = $this->getMockBuilder( Request::class )
			->disableOriginalConstructor()
			->getMock();
		$request->expects( $this->once() )
			->method( 'getRawRequest' )
			->willReturn(
				file_get_contents( __DIR__ . '/../Data/' . $requestFile )
			);
		$request->server = new ServerBag( [
			'HTTP_AUTHORIZATION' => 'Basic ' . base64_encode( 'WikimediaFoundationTest:FoundationTest' )
		] );
		return $request;
	}
}
