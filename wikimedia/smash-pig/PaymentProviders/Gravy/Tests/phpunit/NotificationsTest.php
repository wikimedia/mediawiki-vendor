<?php

use SmashPig\Core\Context;
use SmashPig\Core\Http\Request;
use SmashPig\Core\Http\Response;
use SmashPig\PaymentProviders\Gravy\GravyListener;
use SmashPig\PaymentProviders\Gravy\Jobs\ProcessCaptureRequestJob;
use SmashPig\PaymentProviders\Gravy\Jobs\RecordCaptureJob;
use SmashPig\PaymentProviders\Gravy\Mapper\ResponseMapper;
use SmashPig\PaymentProviders\Gravy\Tests\BaseGravyTestCase;
use Symfony\Component\HttpFoundation\ServerBag;

/**
 * @group Gravy
 */
class NotificationsTest extends BaseGravyTestCase {
	private $jobsGravyQueue;

	/**
	 * @var GravyListener
	 */
	private $gravyListener;

	public function setUp(): void {
		parent::setUp();
		$this->jobsGravyQueue = Context::get()->getGlobalConfiguration()
			->object( 'data-store/jobs-gravy' );
		$this->gravyListener = $this->config->object( 'endpoints/listener' );
	}

	public function testTransactionMessageInvalidRequestEmptyHeader() {
		[ $request, $response ] = $this->getInvalidRequestResponseObjectsEmptyHeader();
		$response->expects( $this->once() )->method( 'setStatusCode' )->with( Response::HTTP_FORBIDDEN, 'Invalid authorization' );
		$request->method( 'getRawRequest' )->willReturn( " " );
		$result = $this->gravyListener->execute( $request, $response );

		$this->assertFalse( $result );
	}

	public function testTransactionMessageInvalidRequestInvalidAuthorizationValue() {
		[ $request, $response ] = $this->getInvalidRequestResponseObjectsInvalidAuth();
		$response->expects( $this->once() )->method( 'setStatusCode' )->with( Response::HTTP_FORBIDDEN, 'Invalid authorization' );
		$request->method( 'getRawRequest' )->willReturn( " " );
		$result = $this->gravyListener->execute( $request, $response );

		$this->assertFalse( $result );
	}

	public function testTransactionMessageValidRequestValidAuthorizationValue(): void {
		[ $request, $response ] = $this->getValidRequestResponseObjects();
		$request->method( 'getRawRequest' )->willReturn( $this->getValidGravyTransactionMessage() );
		$result = $this->gravyListener->execute( $request, $response );
		$this->assertTrue( $result );
	}

	public function testUnknownMessageType(): void {
		[ $request, $response ] = $this->getValidRequestResponseObjects();
		$message = json_decode( $this->getValidGravyTransactionMessage(), true );
		$message['target']['type'] = "Unknown";
		$request->method( 'getRawRequest' )->willReturn( json_encode( $message ) );
		$result = $this->gravyListener->execute( $request, $response );
		$this->assertFalse( $result );
	}

	public function testCapturedTransactionMessage(): void {
		[ $request, $response ] = $this->getValidRequestResponseObjects();
		$responseBody = json_decode( file_get_contents( __DIR__ . '/../Data/successful-transaction.json' ), true );
		$message = json_decode( $this->getValidGravyTransactionMessage(), true );
		$request->method( 'getRawRequest' )->willReturn( json_encode( $message ) );
		$this->mockApi->expects( $this->once() )
			->method( 'getTransaction' )
			->willReturn( $responseBody );
		$result = $this->gravyListener->execute( $request, $response );
		$queued_message = $this->jobsGravyQueue->pop();
		$this->assertEquals( RecordCaptureJob::class, $queued_message['class'] );
		$payload = array_merge(
				[
					"eventDate" => $message["created_at"]
				], ( new ResponseMapper() )->mapFromPaymentResponse( $responseBody )
			);
		$this->assertSame( $payload, $queued_message['payload'] );
		$this->assertTrue( $result );
	}

	public function testAuthorizedTransactionMessage(): void {
		[ $request, $response ] = $this->getValidRequestResponseObjects();
		$responseBody = json_decode( file_get_contents( __DIR__ . '/../Data/approve-transaction.json' ), true );
		$message = json_decode( $this->getValidGravyTransactionMessage(), true );
		$request->method( 'getRawRequest' )->willReturn( json_encode( $message ) );
		$this->mockApi->expects( $this->once() )
			->method( 'getTransaction' )
			->willReturn( $responseBody );
		$result = $this->gravyListener->execute( $request, $response );
		$queued_message = $this->jobsGravyQueue->pop();
		$this->assertEquals( ProcessCaptureRequestJob::class, $queued_message['class'] );
		$payload = array_merge(
			[
				"eventDate" => $message["created_at"]
			], ( new ResponseMapper() )->mapFromPaymentResponse( $responseBody )
		);
		$this->assertSame( $payload, $queued_message['payload'] );
		$this->assertTrue( $result );
	}

	public function getValidRequestResponseObjects( string $request = " " ): array {
		return $this->getMockRequestResponseObjects( [
			"AUTHORIZATION" => "Basic " . base64_encode( $this->config->val( "accounts/webhook/username" ) . ":" . $this->config->val( "accounts/webhook/password" ) )
		] );
	}

	public function getInvalidRequestResponseObjectsEmptyHeader(): array {
		return $this->getMockRequestResponseObjects( [] );
	}

	public function getInvalidRequestResponseObjectsInvalidAuth(): array {
		return $this->getMockRequestResponseObjects( [
			'AUTHORIZATION' => 'Basic YWxhZGRpbjpvcGVuc2VzYW1l'
		] );
	}

	private function getMockRequestResponseObjects( array $headers ): array {
		$request = $this->getMockBuilder( Request::class )->disableOriginalConstructor()->getMock();
		$request->server = $this->getMockBuilder( ServerBag::class )->disableOriginalConstructor()->getMock();
		$request->server->method( 'getHeaders' )->willReturn( $headers );
		$response = $this->getMockBuilder( Response::class )->disableOriginalConstructor()->getMock();

		return [ $request, $response ];
	}

	private function getValidGravyTransactionMessage(): string {
		return '{"type":"event","id":"36d2c101-4db5-4afd-ba4b-8fd9b60764ab","created_at":"2024-07-22T19:56:22.973896+00:00",
        "target":{"type":"transaction","id":"b332ca0a-1dce-4ae6-b27b-04f70db8fae7"},"merchant_account_id":"default"}';
	}
}
