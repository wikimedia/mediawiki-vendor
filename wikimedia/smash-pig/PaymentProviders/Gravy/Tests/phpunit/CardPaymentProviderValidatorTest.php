<?php

namespace SmashPig\PaymentProviders\Gravy\Tests\phpunit;

use PHPUnit\Framework\TestCase;
use SmashPig\PaymentProviders\Gravy\Validators\CardPaymentProviderValidator;
use SmashPig\PaymentProviders\ValidationException;

/**
 * @group Gravy
 * @group Validators
 * @covers \SmashPig\PaymentProviders\Gravy\Validators\CardPaymentProviderValidator
 */
class CardPaymentProviderValidatorTest extends TestCase {

	private CardPaymentProviderValidator $validator;

	protected function setUp(): void {
		parent::setUp();
		$this->validator = new CardPaymentProviderValidator();
	}

	public function testValidateOneTimeCreatePaymentInputSuccess(): void {
		$params = [
			'amount' => '10.00',
			'currency' => 'USD',
			'country' => 'US',
			'order_id' => 'TEST-123',
			'gateway_session_id' => 'session-456',
			'email' => 'test@example.org',
			'first_name' => 'John',
			'last_name' => 'Doe',
		];

		$this->validator->validateOneTimeCreatePaymentInput( $params );
		$this->assertTrue( true );
	}

	public function testValidateOneTimeCreatePaymentInputRequiresCardFields(): void {
		$params = [
			'amount' => '10.00',
			'currency' => 'USD',
			'country' => 'US',
			'order_id' => 'TEST-123',
		];

		try {
			$this->validator->validateOneTimeCreatePaymentInput( $params );
			$this->fail( 'Expected ValidationException' );
		} catch ( ValidationException $e ) {
			$data = $e->getData();
			$this->assertArrayHasKey( 'gateway_session_id', $data );
			$this->assertArrayHasKey( 'email', $data );
			$this->assertArrayHasKey( 'first_name', $data );
			$this->assertArrayHasKey( 'last_name', $data );
		}
	}

	public function testValidateOneTimeCreatePaymentInputBrazilRequiresFiscalNumber(): void {
		$params = [
			'amount' => '10.00',
			'currency' => 'BRL',
			'country' => 'BR',
			'order_id' => 'TEST-123',
			'gateway_session_id' => 'session-456',
			'email' => 'test@example.org',
			'first_name' => 'Maria',
			'last_name' => 'Silva',
		];

		try {
			$this->validator->validateOneTimeCreatePaymentInput( $params );
			$this->fail( 'Expected ValidationException for missing fiscal_number' );
		} catch ( ValidationException $e ) {
			$this->assertArrayHasKey( 'fiscal_number', $e->getData() );
		}
	}

	public function testValidateOneTimeCreatePaymentInputInheritsBaseAmountValidation(): void {
		$params = [
			'amount' => '-10.00',
			'currency' => 'USD',
			'country' => 'US',
			'order_id' => 'TEST-123',
			'gateway_session_id' => 'session-456',
			'email' => 'test@example.org',
			'first_name' => 'John',
			'last_name' => 'Doe',
		];

		$this->expectException( ValidationException::class );
		$this->expectExceptionMessage( 'Invalid amount' );
		$this->validator->validateOneTimeCreatePaymentInput( $params );
	}
}
