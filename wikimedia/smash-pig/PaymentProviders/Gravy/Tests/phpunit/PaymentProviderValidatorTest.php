<?php

namespace SmashPig\PaymentProviders\Gravy\Tests\phpunit;

use PHPUnit\Framework\TestCase;
use SmashPig\PaymentProviders\Gravy\Validators\PaymentProviderValidator;
use SmashPig\PaymentProviders\ValidationException;

/**
 * @group Gravy
 * @group Validators
 * @covers \SmashPig\PaymentProviders\Gravy\Validators\PaymentProviderValidator
 * @covers \SmashPig\PaymentProviders\Gravy\Validators\ValidatorTrait
 */
class PaymentProviderValidatorTest extends TestCase {

	private PaymentProviderValidator $validator;

	protected function setUp(): void {
		parent::setUp();
		$this->validator = $this->getValidator();
	}

	public function testValidateOneTimeCreatePaymentInputSuccess(): void {
		$params = [
			'amount' => '10.00',
			'currency' => 'USD',
			'country' => 'US',
			'order_id' => 'TEST-123',
		];

		$this->validator->validateOneTimeCreatePaymentInput( $params );
		$this->assertTrue( true );
	}

	public function testValidateOneTimeCreatePaymentInputMissingFields(): void {
		$params = [ 'amount' => '10.00' ];

		try {
			$this->validator->validateOneTimeCreatePaymentInput( $params );
			$this->fail( 'Expected ValidationException' );
		} catch ( ValidationException $e ) {
			$data = $e->getData();
			$this->assertArrayHasKey( 'currency', $data );
			$this->assertArrayHasKey( 'country', $data );
			$this->assertArrayHasKey( 'order_id', $data );
		}
	}

	public function testValidateOneTimeCreatePaymentInputInvalidAmount(): void {
		$params = [
			'amount' => '-10.00',
			'currency' => 'USD',
			'country' => 'US',
			'order_id' => 'TEST-123',
		];

		$this->expectException( ValidationException::class );
		$this->expectExceptionMessage( 'Invalid amount' );
		$this->validator->validateOneTimeCreatePaymentInput( $params );
	}

	public function testValidateRecurringCreatePaymentInputSuccess(): void {
		$params = [
			'recurring_payment_token' => 'token-123',
			'amount' => '10.00',
			'currency' => 'USD',
			'country' => 'US',
			'order_id' => 'TEST-123',
			'email' => 'test@example.org',
		];

		$this->validator->validateRecurringCreatePaymentInput( $params );
		$this->assertTrue( true );
	}

	public function testValidateRecurringRequiresFiscalNumberForBrazil(): void {
		$params = [
			'recurring_payment_token' => 'token-123',
			'amount' => '10.00',
			'currency' => 'BRL',
			'country' => 'BR',
			'order_id' => 'TEST-123',
			'email' => 'test@example.org',
		];

		try {
			$this->validator->validateRecurringCreatePaymentInput( $params );
			$this->fail( 'Expected ValidationException for missing fiscal_number' );
		} catch ( ValidationException $e ) {
			$this->assertArrayHasKey( 'fiscal_number', $e->getData() );
		}
	}

	public function testValidateCreatePaymentInputRoutesCorrectly(): void {
		// With token -> recurring (requires email)
		$recurringParams = [
			'recurring_payment_token' => 'token-123',
			'amount' => '10.00',
			'currency' => 'USD',
			'country' => 'US',
			'order_id' => 'TEST-123',
		];

		try {
			$this->validator->validateCreatePaymentInput( $recurringParams );
			$this->fail( 'Expected ValidationException' );
		} catch ( ValidationException $e ) {
			$this->assertArrayHasKey( 'email', $e->getData() );
		}

		// Without token -> one-time (no email required at base level)
		$oneTimeParams = [
			'amount' => '10.00',
			'currency' => 'USD',
			'country' => 'US',
			'order_id' => 'TEST-123',
		];

		$this->validator->validateCreatePaymentInput( $oneTimeParams );
		$this->assertTrue( true );
	}

	public function testValidateRefundInputPartialRequiresCurrency(): void {
		$params = [
			'gateway_txn_id' => 'txn-123',
			'amount' => '5.00',
		];

		try {
			$this->validator->validateRefundInput( $params );
			$this->fail( 'Expected ValidationException' );
		} catch ( ValidationException $e ) {
			$this->assertArrayHasKey( 'currency', $e->getData() );
		}
	}

	/**
	 * PaymentProviderValidator is abstract, so we use an anonymous class to test it directly.
	 */
	private function getValidator(): PaymentProviderValidator {
		return new class extends PaymentProviderValidator {
		};
	}
}
