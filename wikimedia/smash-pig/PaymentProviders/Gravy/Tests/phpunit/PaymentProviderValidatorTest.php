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
			'first_name' => 'test',
			'last_name' => 'example',
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

	/**
	 * @dataProvider provideRecurringNameTestData
	 */
	public function testRecurringNameCheckSplitsFullName( array $params, ?string $expectedException, array $expectedResults = [] ): void {
		if ( $expectedException ) {
			$this->expectException( $expectedException );

			// If you need to assert specific keys inside the ValidationException data:
			try {
				$this->validator->validateRecurringCreatePaymentInput( $params );
			} catch ( ValidationException $e ) {
				foreach ( $expectedResults as $key ) {
					$this->assertArrayHasKey( $key, $e->getData() );
				}
				throw $e; // Re-throw so expectException catches it and passes the test
			}
		} else {
			// Test successful split execution
			$this->validator->validateRecurringCreatePaymentInput( $params );
			$this->assertEquals( $expectedResults['first_name'], $params['first_name'] );
			if ( isset( $expectedResults['last_name'] ) ) {
				$this->assertEquals( $expectedResults['last_name'], $params['last_name'] );
			}
		}
	}

	/**
	 * @dataProvider provideRecurringProcessorContactIdData
	 */
	public function testRecurringValidateProcessorContactId( array $params, array $expectedResults = [] ): void {
		// Test successful split execution
		$this->validator->validateRecurringCreatePaymentInput( $params );
		$this->assertEquals( $expectedResults['processor_contact_id'], $params['processor_contact_id'] );
	}

	public function provideRecurringProcessorContactIdData(): array {
		$baseParams = [
			'recurring_payment_token' => 'token-123',
			'amount' => '10.00',
			'currency' => 'USD',
			'country' => 'US',
			'order_id' => 'TEST-123',
			'email' => 'test@example.org',
		];

		return [
			'not uuid processor_contact_id' => [
				array_merge( $baseParams, [ 'processor_contact_id' => '123456789.1' ] ),
				[ 'processor_contact_id' => null ]
			],
			'empty processor_contact_id' => [
				array_merge( $baseParams, [ 'processor_contact_id' => '' ] ),
				[ 'processor_contact_id' => null ]
			],
			'right processor_contact_id' => [
				array_merge( $baseParams, [ 'processor_contact_id' => '12345678-1234-4234-8234-123456789012' ] ),
				[ 'processor_contact_id' => '12345678-1234-4234-8234-123456789012' ] // Expected outcome
			]
		];
	}

	public function provideRecurringNameTestData(): array {
		$baseParams = [
			'recurring_payment_token' => 'token-123',
			'amount' => '10.00',
			'currency' => 'USD',
			'country' => 'US',
			'order_id' => 'TEST-123',
			'email' => 'test@example.org',
		];

		return [
			'Missing both names not throws exception' => [
				array_merge( $baseParams, [] ),
				null, // No exception expected
				[ 'first_name' => null ] // Expected outcome
			],
			'Missing last name with single-word first name not throws exception' => [
				array_merge( $baseParams, [ 'first_name' => 'asdf' ] ),
				null, // No exception expected
				[ 'first_name' => 'asdf' ] // Expected outcome
			],
			'Missing first name with single-word last name not throws exception' => [
				array_merge( $baseParams, [ 'first_name' => '', 'last_name' => 'asdf' ] ),
				null, // No exception expected
				[ 'first_name' => 'asdf' ] // Expected outcome
			],
			'Missing last name with multi-word first name successfully splits' => [
				array_merge( $baseParams, [ 'first_name' => 'asdf ssss', 'last_name' => '' ] ),
				null, // No exception expected
				[ 'first_name' => 'asdf', 'last_name' => 'ssss' ] // Expected outcome
			],
			'Missing first name with multi-word last name successfully splits' => [
				array_merge( $baseParams, [ 'first_name' => '', 'last_name' => 'xxx L yyyy' ] ),
				null, // No exception expected
				[ 'first_name' => 'xxx', 'last_name' => 'L yyyy' ] // Expected outcome
			],
			'Missing last name with multi-word split by . successfully splits' => [
				array_merge( $baseParams, [ 'first_name' => 'xxxx.yyy.', 'last_name' => '' ] ),
				null, // No exception expected
				[ 'first_name' => 'xxxx', 'last_name' => 'yyy.' ] // Expected outcome
			],
		];
	}

	public function testValidateCreatePaymentInputRoutesCorrectly(): void {
		// With token -> recurring (requires email)
		$recurringParams = [
			'recurring_payment_token' => 'token-123',
			'amount' => '10.00',
			'currency' => 'USD',
			'country' => 'US',
			'order_id' => 'TEST-123'
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
