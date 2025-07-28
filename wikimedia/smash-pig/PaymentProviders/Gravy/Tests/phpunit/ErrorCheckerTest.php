<?php

namespace SmashPig\PaymentProviders\Gravy\Tests\phpunit;

use SmashPig\PaymentProviders\Gravy\Errors\ErrorChecker;
use SmashPig\PaymentProviders\Gravy\Tests\BaseGravyTestCase;

class ErrorCheckerTest extends BaseGravyTestCase {

	/**
	 * @var ErrorChecker
	 */
	protected ErrorChecker $errorChecker;

	public function setUp(): void {
		parent::setUp();
		$this->errorChecker = new ErrorChecker();
	}

	public function testResponseHasErrorsWithErrorResponseType(): void {
		$testResponse = [
			'type' => 'error',
			'code' => 'unauthorized',
			'status' => 401,
			'message' => 'No valid API authentication found',
			'details' => []
		];

		$this->assertTrue( $this->errorChecker->responseHasErrors( $testResponse ) );
	}

	public function testResponseHasErrorsWithErrorCode(): void {
		$testResponse = [
			'type' => 'transaction',
			'method' => 'trustly',
			'instrument_type' => 'redirect',
			'error_code' => 'cancelled_buyer_approval',
			'payment_service' => [
				'type' => 'payment-service',
				'id' => 'c9695cda-4dd9-4e8b-8f52-b654e46dda23',
				'payment_service_definition_id' => 'trustly-trustly',
				'method' => 'trustly',
				'display_name' => 'Trustly (US)'
			],
			'buyer' => [
				'type' => 'buyer',
				'external_identifier' => 'test@example.com'
			],
			'created_at' => '2025-07-02T02:09:57.337225+00:00',
			'updated_at' => '2025-07-02T02:10:30.409685+00:00',
			'payment_source' => 'ecommerce',
			'statement_descriptor' => [
				'description' => 'Wikimedia Foundation'
			],
			'payment_service_transaction_id' => '7IzlaLe3qukc4waoRLpM7y',
		];

		$this->assertTrue( $this->errorChecker->responseHasErrors( $testResponse ) );
	}

	public function testResponseHasErrorsWithFailedIntentOutcome(): void {
		$testResponse = [
			'type' => 'transaction',
			'id' => '61df177c-76b3-4f0c-80fb-d1ad53764c91',
			'reconciliation_id' => '2ygCx7feN98uAcJc6rv237',
			'merchant_account_id' => 'default',
			'created_at' => '2024-08-06T18:26:27.762364+00:00',
			'updated_at' => '2024-08-06T18:26:59.664571+00:00',
			'amount' => 1000,
			'authorized_amount' => 0,
			'captured_amount' => 0,
			'refunded_amount' => 0,
			'currency' => 'USD',
			'country' => 'US',
			'external_identifier' => '166.3',
			'status' => 'authorization_declined',
			'intent' => 'authorize',
			'intent_outcome' => 'failed',
			'payment_method' => [
				'type' => 'payment-method',
				'method' => 'card',
				'mode' => 'card',
				'label' => '1111',
				'scheme' => 'visa'
			]
		];

		$this->assertTrue( $this->errorChecker->responseHasErrors( $testResponse ) );
	}

	public function testResponseHasErrorsWith3DSecureError(): void {
		$testResponse = [
			'type' => 'transaction',
			'id' => '61df177c-76b3-4f0c-80fb-d1ad53764c91',
			'reconciliation_id' => '2ygCx7feN98uAcJc6rv237',
			'merchant_account_id' => 'default',
			'created_at' => '2024-08-06T18:26:27.762364+00:00',
			'updated_at' => '2024-08-06T18:26:59.664571+00:00',
			'amount' => 1000,
			'authorized_amount' => 0,
			'captured_amount' => 0,
			'refunded_amount' => 0,
			'currency' => 'USD',
			'country' => 'US',
			'external_identifier' => '166.3',
			'intent' => 'authorize',
			'payment_method' => [
				'type' => 'payment-method',
				'method' => 'card',
				'mode' => 'card',
				'label' => '1111',
				'scheme' => 'visa',
				'expiration_date' => '03/30'
			],
			'three_d_secure' => [
				'version' => '2.1.0',
				'status' => 'error',
				'method' => null,
				'response_data' => null,
				'error_data' => [
					'code' => '305',
					'description' => 'No valid test input found',
					'detail' => 'Unknown test, see valid tests here https://docs.3dsecure.io/3dsv2/sandbox.html',
					'component' => 'D'
				]
			]
		];

		$this->assertTrue( $this->errorChecker->responseHasErrors( $testResponse ) );
	}

	public function testResponseHasErrorsWithFailedPaymentStatus(): void {
		$testResponse = [
			'type' => 'transaction',
			'id' => '61df177c-76b3-4f0c-80fb-d1ad53764c91',
			'reconciliation_id' => '2ygCx7feN98uAcJc6rv237',
			'merchant_account_id' => 'default',
			'created_at' => '2024-08-06T18:26:27.762364+00:00',
			'updated_at' => '2024-08-06T18:26:59.664571+00:00',
			'amount' => 1000,
			'authorized_amount' => 0,
			'captured_amount' => 0,
			'refunded_amount' => 0,
			'currency' => 'USD',
			'country' => 'US',
			'external_identifier' => '166.3',
			'status' => 'authorization_declined',
			'intent' => 'authorize',
			'payment_method' => [
				'type' => 'payment-method',
				'method' => 'card',
				'mode' => 'card',
				'label' => '1111',
				'scheme' => 'visa',
				'expiration_date' => '03/30'
			]
		];

		$this->assertTrue( $this->errorChecker->responseHasErrors( $testResponse ) );
	}

	public function testResponseHasNoErrors(): void {
		$testResponse = [
			'type' => 'transaction',
			'id' => 'b332ca0a-1dce-4ae6-b27b-04f70db8fae7',
			'reconciliation_id' => '5S8puSJHjzWXtSEbjHoiP9',
			'merchant_account_id' => 'default',
			'created_at' => '2024-07-22T19:56:09.406614+00:00',
			'updated_at' => '2024-07-22T19:56:22.853932+00:00',
			'amount' => 3500,
			'authorized_amount' => 3500,
			'captured_amount' => 3500,
			'refunded_amount' => 0,
			'currency' => 'USD',
			'country' => 'US',
			'external_identifier' => '12354846.6',
			'status' => 'capture_succeeded',
			'intent' => 'authorize',
			'payment_method' => [
				'type' => 'payment-method',
				'method' => 'card',
				'mode' => 'card',
				'label' => '1111',
				'scheme' => 'visa',
				'expiration_date' => '03/30'
			],
			'method' => 'card',
			'instrument_type' => 'pan',
			'error_code' => null,
			'payment_service' => [
				'id' => '9996be63-1e6f-4290-9d6e-b70f5d9b6bf8',
				'type' => 'payment-service',
				'payment_service_definition_id' => 'adyen-card',
				'method' => 'card',
				'display_name' => 'Adyen'
			],
			'intent_outcome' => 'succeeded'
		];

		$this->assertFalse( $this->errorChecker->responseHasErrors( $testResponse ) );
	}

	public function testResponseHasErrorsWithMultipleErrorIndicators(): void {
		$testResponse = [
			'type' => 'transaction',
			'id' => 'f010a662-757e-4881-bad2-65feb1762a1e',
			'reconciliation_id' => '7IzlaLe3qukc4waoRLpM7y',
			'merchant_account_id' => 'default',
			'currency' => 'USD',
			'amount' => 10400,
			'status' => 'authorization_failed',
			'country' => 'US',
			'external_identifier' => '232515486.1',
			'intent' => 'authorize',
			'payment_method' => [
				'type' => 'payment-method',
				'approval_url' => 'https://cdn.wikimedia.gr4vy.app/connectors/trustly/index.html',
				'country' => 'US',
				'currency' => 'USD',
				'method' => 'trustly',
				'mode' => 'redirect'
			],
			'method' => 'trustly',
			'instrument_type' => 'redirect',
			'error_code' => 'cancelled_buyer_approval',
			'payment_service' => [
				'type' => 'payment-service',
				'id' => 'c9695cda-4dd9-4e8b-8f52-b654e46dda23',
				'payment_service_definition_id' => 'trustly-trustly',
				'method' => 'trustly',
				'display_name' => 'Trustly (US)'
			],
			'buyer' => [
				'type' => 'buyer',
				'external_identifier' => 'test@example.com'
			],
			'created_at' => '2025-07-02T02:09:57.337225+00:00',
			'updated_at' => '2025-07-02T02:10:30.409685+00:00',
			'payment_source' => 'ecommerce',
			'statement_descriptor' => [
				'description' => 'Wikimedia Foundation'
			],
			'payment_service_transaction_id' => '7IzlaLe3qukc4waoRLpM7y',
			'intent_outcome' => 'failed'
		];

		$this->assertTrue( $this->errorChecker->responseHasErrors( $testResponse ) );
	}
}
