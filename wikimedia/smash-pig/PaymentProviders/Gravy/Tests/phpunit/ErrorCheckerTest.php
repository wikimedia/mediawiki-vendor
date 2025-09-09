<?php

namespace SmashPig\PaymentProviders\Gravy\Tests\phpunit;

use SmashPig\PaymentProviders\Gravy\Errors\ErrorChecker;
use SmashPig\PaymentProviders\Gravy\Errors\ErrorType;
use SmashPig\PaymentProviders\Gravy\Tests\BaseGravyTestCase;

/**
 * @group Gravy
 */
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

	public function testGetResponseErrorDetailsWithErrorResponseType(): void {
		$testResponse = [
			'type' => 'error',
			'code' => 'unauthorized',
			'status' => 401,
			'message' => 'No valid API authentication found',
			'details' => []
		];

		$expectedDetails = [
			'error_type' => ErrorType::RESPONSE_TYPE->value,
			'error_code' => 401
		];

		$this->assertEquals( $expectedDetails, $this->errorChecker->getResponseErrorDetails( $testResponse ) );
	}

	public function testGetResponseErrorDetailsWithErrorCode(): void {
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

		$expectedDetails = [
			'error_type' => ErrorType::ERROR_CODE->value,
			'error_code' => 'cancelled_buyer_approval'
		];

		$this->assertEquals( $expectedDetails, $this->errorChecker->getResponseErrorDetails( $testResponse ) );
	}

	public function testGetResponseErrorDetailsWithFailedIntentOutcome(): void {
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

		$expectedDetails = [
			'error_type' => ErrorType::FAILED_INTENT->value,
			'error_code' => 'failed'
		];

		$this->assertEquals( $expectedDetails, $this->errorChecker->getResponseErrorDetails( $testResponse ) );
	}

	public function testGetResponseErrorDetailsWith3DSecureError(): void {
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

		$expectedDetails = [
			'error_type' => ErrorType::THREE_D_SECURE->value,
			'error_code' => 'error'
		];

		$this->assertEquals( $expectedDetails, $this->errorChecker->getResponseErrorDetails( $testResponse ) );
	}

	public function testGetResponseErrorDetailsWithFailedPaymentStatus(): void {
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

		$expectedDetails = [
			'error_type' => ErrorType::FAILED_PAYMENT->value,
			'error_code' => 'authorization_declined'
		];

		$this->assertEquals( $expectedDetails, $this->errorChecker->getResponseErrorDetails( $testResponse ) );
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

	public function testGetResponseErrorDetailsWithInvalidTaxIdentifier(): void {
		$testResponse = [
			"type" => "transaction",
			"id" => "66e8051a-9f1a-4983-acea-87641fa8a907",
			"reconciliation_id" => "38BFWUdYAVYQxPLZSqN7At",
			"merchant_account_id" => "default",
			"currency" => "BRL",
			"amount" => 1700,
			"status" => "authorization_failed",
			"authorized_amount" => 0,
			"captured_amount" => 0,
			"refunded_amount" => 0,
			"settled_currency" => null,
			"settled_amount" => 0,
			"settled" => false,
			"country" => "BR",
			"external_identifier" => "134021808.1",
			"intent" => "capture",
			"payment_method" => [
				"type" => "payment-method",
				"approval_url" => null,
				"country" => "BR",
				"currency" => "BRL",
				"details" => null,
				"expiration_date" => null,
				"fingerprint" => null,
				"label" => null,
				"last_replaced_at" => null,
				"method" => "pix",
				"mode" => "redirect",
				"scheme" => null,
				"id" => null,
				"approval_target" => null,
				"external_identifier" => null,
				"payment_account_reference" => null
			],
			"method" => "pix",
			"instrument_type" => "redirect",
			"error_code" => "invalid_tax_identifier",
			"payment_service" => [
				"type" => "payment-service",
				"id" => "ed3f243b-fe28-4f85-bece-3c870e59e539",
				"payment_service_definition_id" => "dlocal-pix",
				"method" => "pix",
				"display_name" => "Pix"
			],
			"pending_review" => false,
			"buyer" => [
				"type" => "buyer",
				"id" => null,
				"display_name" => null,
				"external_identifier" => "test@test.com",
				"billing_details" => [
					"first_name" => "Test",
					"last_name" => "McTesty",
					"email_address" => "test@test.com",
					"phone_number" => null,
					"address" => [
						"city" => null,
						"country" => "BR",
						"postal_code" => null,
						"state" => null,
						"state_code" => null,
						"house_number_or_name" => null,
						"line1" => null,
						"line2" => null,
						"organization" => null
					],
					"tax_id" => [
						"value" => "12345678912",
						"kind" => "br.cpf"
					]
				],
				"account_number" => null
			],
			"raw_response_code" => "342",
			"raw_response_description" => "Invalid document.",
			"shipping_details" => null,
			"checkout_session_id" => null,
			"gift_card_redemptions" => [],
			"gift_card_service" => null,
			"created_at" => "2025-08-15T14:13:20.383039+00:00",
			"updated_at" => "2025-08-15T14:13:21.700609+00:00",
			"airline" => null,
			"auth_response_code" => null,
			"avs_response_code" => null,
			"cvv_response_code" => null,
			"anti_fraud_decision" => null,
			"payment_source" => "ecommerce",
			"merchant_initiated" => false,
			"is_subsequent_payment" => false,
			"cart_items" => [],
			"statement_descriptor" => [
				"name" => null,
				"description" => "Wikimedia Foundation",
				"city" => null,
				"country" => null,
				"phone_number" => null,
				"url" => null
			],
			"scheme_transaction_id" => null,
			"three_d_secure" => null,
			"payment_service_transaction_id" => "R-648-x1k9ug41-9f2fdgcvkp26p2-f1eosjlddagc",
			"additional_identifiers" => [
				"payment_service_authorization_id" => null,
				"payment_service_capture_id" => null,
				"payment_service_processor_id" => null
			],
			"metadata" => null,
			"authorized_at" => null,
			"captured_at" => null,
			"voided_at" => null,
			"approval_expires_at" => null,
			"buyer_approval_timedout_at" => null,
			"intent_outcome" => "failed",
			"multi_tender" => false,
			"account_funding_transaction" => false,
			"recipient" => null,
			"merchant_advice_code" => null,
			"installment_count" => null
		];

		$expectedDetails = [
			'error_type' => ErrorType::ERROR_CODE->value,
			'error_code' => 'invalid_tax_identifier'
		];

		$this->assertEquals( $expectedDetails, $this->errorChecker->getResponseErrorDetails( $testResponse ) );
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

	public function testGetResponseErrorDetailsWithNoErrors(): void {
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

		$expectedDetails = [];

		$this->assertEquals( $expectedDetails, $this->errorChecker->getResponseErrorDetails( $testResponse ) );
	}

	public function testGetResponseErrorDetailsWithMultipleErrorIndicators(): void {
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

		// Should return the first detected error type (error_code comes before intent_outcome in the method)
		$expectedDetails = [
			'error_type' => ErrorType::ERROR_CODE->value,
			'error_code' => 'cancelled_buyer_approval'
		];

		$this->assertEquals( $expectedDetails, $this->errorChecker->getResponseErrorDetails( $testResponse ) );
	}

	public function testGetResponseErrorDetailsWithMissingStatus(): void {
		$testResponse = [
			'type' => 'error',
			'code' => 'unauthorized',
			'message' => 'No valid API authentication found',
			'details' => []
		];

		$expectedDetails = [
			'error_type' => ErrorType::RESPONSE_TYPE->value,
			'error_code' => 'error_response_unknown_error_code'
		];

		$this->assertEquals( $expectedDetails, $this->errorChecker->getResponseErrorDetails( $testResponse ) );
	}
}
