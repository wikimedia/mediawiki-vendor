<?php

namespace SmashPig\PaymentProviders\Gravy\Tests\phpunit;

use SmashPig\PaymentProviders\Gravy\Tests\BaseGravyTestCase;
use SmashPig\PaymentProviders\Gravy\Validators\ListenerValidator;
use SmashPig\PaymentProviders\ValidationException;

/**
 * @group Gravy
 * @group Validators
 * @covers \SmashPig\PaymentProviders\Gravy\Validators\ListenerValidator
 */
class ListenerValidatorTest extends BaseGravyTestCase {

	private ListenerValidator $validator;

	public function setUp(): void {
		parent::setUp();
		$this->validator = new ListenerValidator();
	}

	public function testValidateWebhookEventHeaderSuccess(): void {
		$expectedAuth = 'Basic ' . base64_encode( 'WikimediaFoundationTest:FoundationTest' );
		$params = [ 'AUTHORIZATION' => $expectedAuth ];

		$this->validator->validateWebhookEventHeader( $params, $this->config );
		$this->assertTrue( true );
	}

	public function testValidateWebhookEventHeaderMissingAuthorization(): void {
		$this->expectException( ValidationException::class );
		$this->validator->validateWebhookEventHeader( [], $this->config );
	}

	public function testValidateWebhookEventHeaderInvalidCredentials(): void {
		$params = [ 'AUTHORIZATION' => 'Basic ' . base64_encode( 'wrong:credentials' ) ];

		try {
			$this->validator->validateWebhookEventHeader( $params, $this->config );
			$this->fail( 'Expected ValidationException for invalid credentials' );
		} catch ( ValidationException $e ) {
			$this->assertStringContainsString( 'Invalid Authorisation header', $e->getMessage() );
		}
	}
}
