<?php

namespace SmashPig\PaymentProviders\Gravy\Tests\phpunit;

use PHPUnit\Framework\TestCase;
use SmashPig\PaymentData\ErrorCode;
use SmashPig\PaymentProviders\Gravy\Factories\GravyCreatePaymentResponseFactory;
use SmashPig\PaymentProviders\Responses\PaymentProviderResponse;

/**
 * @group Gravy
 */
class GravyPaymentResponseFactoryTest extends TestCase {

	/**
	 * Test that the factory sets up the PaymentProviderResponse correctly from a normalized response
	 *
	 * @return void
	 */
	public function testBuildPaymentResponseFromNormalizedCreatePaymentResponseData(): void {
		$testNormalizedResponseData = $this->getTestNormalizedResponseData();
		$gravyPaymentProviderResponse = GravyCreatePaymentResponseFactory::fromNormalizedResponse( $testNormalizedResponseData );

		$this->assertInstanceOf( PaymentProviderResponse::class, $gravyPaymentProviderResponse );
		$this->assertTrue( $gravyPaymentProviderResponse->isSuccessful() );
		$this->assertEquals( 'fe26475d-ec3e-4884-9553-f7356683f7f9', $gravyPaymentProviderResponse->getGatewayTxnId() );
		$this->assertEquals( 'pending-poke', $gravyPaymentProviderResponse->getStatus() );
		$this->assertEquals( 'processing', $gravyPaymentProviderResponse->getRawStatus() );
		$this->assertEquals( $testNormalizedResponseData['raw_response'], $gravyPaymentProviderResponse->getRawResponse() );
		$this->assertEquals( $testNormalizedResponseData['risk_scores'], $gravyPaymentProviderResponse->getRiskScores() );
		$this->assertCount( 0, $gravyPaymentProviderResponse->getErrors() );
	}

	/**
	 * A failed transaction should still populate donor / backend processor / payment method
	 * details, in addition to the error, so we know what backend processor was used even
	 * when the authorization failed.
	 *
	 * @return void
	 */
	public function testBuildPaymentResponseFromNormalizedFailedCreatePaymentResponseData(): void {
		$testNormalizedResponseData = $this->getTestFailedNormalizedResponseData();
		$gravyPaymentProviderResponse = GravyCreatePaymentResponseFactory::fromNormalizedResponse( $testNormalizedResponseData );

		$this->assertFalse( $gravyPaymentProviderResponse->isSuccessful() );
		$this->assertEquals( 'failed', $gravyPaymentProviderResponse->getStatus() );
		$this->assertEquals( 'authorization_declined', $gravyPaymentProviderResponse->getRawStatus() );
		$this->assertCount( 1, $gravyPaymentProviderResponse->getErrors() );

		$this->assertEquals( 'fe26475d-ec3e-4884-9553-f7356683f7f9', $gravyPaymentProviderResponse->getGatewayTxnId() );
		$this->assertEquals( 'trustly', $gravyPaymentProviderResponse->getBackendProcessor() );
		$this->assertEquals( '7jZXl4gBUNl0CnaLEnfXbt', $gravyPaymentProviderResponse->getPaymentOrchestratorReconciliationId() );
		$this->assertEquals( 'John', $gravyPaymentProviderResponse->getDonorDetails()->getFirstName() );
	}

	/**
	 * When the error happens before Gravy ever collected buyer / payment method / payment
	 * service data (eg. a request-level error), those fields should come back empty rather
	 * than throwing or getting populated with placeholder data.
	 *
	 * @return void
	 */
	public function testBuildPaymentResponseFromNormalizedFailedResponseWithNoCollectedDetails(): void {
		$testNormalizedResponseData = $this->getTestFailedNormalizedResponseDataWithNoCollectedDetails();
		$gravyPaymentProviderResponse = GravyCreatePaymentResponseFactory::fromNormalizedResponse( $testNormalizedResponseData );

		$this->assertFalse( $gravyPaymentProviderResponse->isSuccessful() );
		$this->assertEquals( 'failed', $gravyPaymentProviderResponse->getStatus() );
		$this->assertCount( 1, $gravyPaymentProviderResponse->getErrors() );

		$this->assertNull( $gravyPaymentProviderResponse->getGatewayTxnId() );
		$this->assertNull( $gravyPaymentProviderResponse->getAmount() );
		$this->assertNull( $gravyPaymentProviderResponse->getCurrency() );
		$this->assertNull( $gravyPaymentProviderResponse->getPaymentMethod() );
		$this->assertNull( $gravyPaymentProviderResponse->getPaymentSubmethod() );
		$this->assertNull( $gravyPaymentProviderResponse->getBackendProcessor() );

		// setDonorDetails always builds a DonorDetails, but with empty fields when none were collected.
		$this->assertSame( '', $gravyPaymentProviderResponse->getDonorDetails()->getFirstName() );
		$this->assertSame( '', $gravyPaymentProviderResponse->getDonorDetails()->getEmail() );
	}

	/**
	 * @return array
	 */
	private function getTestFailedNormalizedResponseDataWithNoCollectedDetails(): array {
		return [
			'is_successful' => false,
			'status' => 'failed',
			'raw_status' => 429,
			'message' => 'Too many requests',
			'description' => 'too_many_requests',
			'code' => 1,
			'raw_response' => [ 'type' => 'error', 'status' => 429 ],
		];
	}

	/**
	 * A validation error can point at a specific field (via a JSON pointer in the raw
	 * response's `details`) with no buyer/payment_method/payment_service context at all,
	 * eg. a stored payment method that failed before a transaction was ever attempted.
	 * The field-level detail should still surface as a validation error.
	 *
	 * @return void
	 */
	public function testBuildPaymentResponseFromNormalizedValidationErrorWithNoOtherContext(): void {
		$testNormalizedResponseData = $this->getTestValidationErrorNormalizedResponseData();
		$gravyPaymentProviderResponse = GravyCreatePaymentResponseFactory::fromNormalizedResponse( $testNormalizedResponseData );

		$this->assertFalse( $gravyPaymentProviderResponse->isSuccessful() );
		$this->assertEquals( 'failed', $gravyPaymentProviderResponse->getStatus() );
		$this->assertCount( 0, $gravyPaymentProviderResponse->getErrors() );
		$this->assertCount( 1, $gravyPaymentProviderResponse->getValidationErrors() );

		$validationError = $gravyPaymentProviderResponse->getValidationErrors()[0];
		$this->assertEquals( 'payment_method/id', $validationError->getField() );
		$this->assertEquals(
			'Payment method has not been successfully stored. Current status: failed.',
			$validationError->getDebugMessage()
		);

		$this->assertNull( $gravyPaymentProviderResponse->getGatewayTxnId() );
		$this->assertNull( $gravyPaymentProviderResponse->getBackendProcessor() );
	}

	/**
	 * @return array
	 */
	private function getTestValidationErrorNormalizedResponseData(): array {
		return [
			'is_successful' => false,
			'status' => 'failed',
			'raw_status' => 400,
			'message' => 'bad_request',
			'description' => 'Request failed validation',
			'code' => ErrorCode::VALIDATION,
			'raw_response' => [
				'type' => 'error',
				'code' => 'bad_request',
				'status' => 400,
				'message' => 'Request failed validation',
				'details' => [
					[
						'location' => 'body',
						'pointer' => '/payment_method/id',
						'message' => 'Payment method has not been successfully stored. Current status: failed.',
						'type' => 'value_error',
					],
				],
			],
		];
	}

	/**
	 * @return array
	 */
	private function getTestFailedNormalizedResponseData(): array {
		return [
			'is_successful' => false,
			'gateway_txn_id' => 'fe26475d-ec3e-4884-9553-f7356683f7f9',
			'payment_orchestrator_reconciliation_id' => '7jZXl4gBUNl0CnaLEnfXbt',
			'order_id' => 'user-789123',
			'raw_status' => 'authorization_declined',
			'status' => 'failed',
			'message' => 'authorization_declined',
			'description' => 'insufficient_funds',
			'code' => 1,
			'backend_processor' => 'trustly',
			'donor_details' => [
				'first_name' => 'John',
				'last_name' => 'Lunn',
				'email_address' => 'john@example.com',
				'phone_number' => '+1234567890',
			],
			'raw_response' => [ 'status' => 'authorization_declined' ],
		];
	}

	/**
	 * This is a meaty sample, so it should allow us to confirm everything gets set up as expected
	 *
	 * @return array
	 */
	private function getTestNormalizedResponseData(): array {
		return [
			'is_successful' => true,
			'gateway_txn_id' => 'fe26475d-ec3e-4884-9553-f7356683f7f9',
			'payment_orchestrator_reconciliation_id' => '7jZXl4gBUNl0CnaLEnfXbt',
			'amount' => 12.99,
			'currency' => 'USD',
			'order_id' => 'user-789123',
			'raw_status' => 'processing',
			'status' => 'pending-poke',
			'risk_scores' => [
				'cvv' => 50,
				'avs' => 0
			],
			'raw_response' =>
				[
					'type' => 'transaction',
					'id' => 'fe26475d-ec3e-4884-9553-f7356683f7f9',
					'amount' => 1299,
					'auth_response_code' => '00',
					'authorized_amount' => 1299,
					'authorized_at' => '2013-07-16T19:23:00.000+00:00',
					'approval_expires_at' => '2013-07-16T19:23:00.000+00:00',
					'avs_response_code' => 'partial_match_address',
					'buyer' =>
						[
							'type' => 'buyer',
							'id' => 'fe26475d-ec3e-4884-9553-f7356683f7f9',
							'billing_details' =>
								[
									'type' => 'billing-details',
									'first_name' => 'John',
									'last_name' => 'Lunn',
									'email_address' => 'john@example.com',
									'phone_number' => '+1234567890',
									'address' =>
										[
											'city' => 'London',
											'country' => 'GB',
											'postal_code' => '789123',
											'state' => 'Greater London',
											'state_code' => 'GB-LND',
											'house_number_or_name' => '10',
											'line1' => '10 Oxford Street',
											'line2' => 'New Oxford Court',
											'organization' => 'Gr4vy',
										],
									'tax_id' =>
										[
											'value' => '12345678931',
											'kind' => 'gb.vat',
										],
								],
							'display_name' => 'John L.',
							'external_identifier' => 'user-789123',
						],
					'captured_amount' => 999,
					'captured_at' => '2013-07-16T19:23:00.000+00:00',
					'checkout_session_id' => 'fe26475d-ec3e-4884-9553-f7356683f7f9',
					'country' => 'US',
					'created_at' => '2013-07-16T19:23:00.000+00:00',
					'currency' => 'USD',
					'cvv_response_code' => 'match',
					'external_identifier' => 'user-789123',
					'instrument_type' => 'network_token',
					'intent' => 'authorize',
					'intent_outcome' => 'pending',
					'is_subsequent_payment' => true,
					'merchant_account_id' => 'default',
					'merchant_initiated' => true,
					'metadata' =>
						[
							'key' => 'value',
						],
					'method' => 'card',
					'multi_tender' => true,
					'payment_method' =>
						[
							'type' => 'payment-method',
							'id' => '77a76f7e-d2de-4bbc-ada9-d6a0015e6bd5',
							'approval_target' => 'any',
							'approval_url' => 'https://api.example.app.gr4vy.com/payment-methods/ffc88ec9-e1ee-45ba-993d-b5902c3b2a8c/approve',
							'country' => 'US',
							'currency' => 'USD',
							'details' =>
								[
									'card_type' => 'credit',
									'bin' => '412345',
								],
							'expiration_date' => '11/25',
							'external_identifier' => 'user-789123',
							'label' => '1111',
							'last_replaced_at' => '2023-07-26T19:23:00.000+00:00',
							'method' => 'card',
							'payment_account_reference' => 'V0010014629724763377327521982',
							'scheme' => 'visa',
							'fingerprint' => '20eb353620155d2b5fc864cc46a73ea77cb92c725238650839da1813fa987a17',
						],
					'payment_service' =>
						[
							'type' => 'payment-service',
							'id' => 'stripe-card-faaad066-30b4-4997-a438-242b0752d7e1',
							'display_name' => 'Stripe (Main)',
							'method' => 'card',
							'payment_service_definition_id' => 'stripe-card',
						],
					'payment_service_transaction_id' => 'charge_xYqd43gySMtori',
					'payment_source' => 'recurring',
					'pending_review' => true,
					'raw_response_code' => 'incorrect-zip',
					'raw_response_description' => 'The card\'s postal code is incorrect. Check the card\'s postal code or use a different card.',
					'reconciliation_id' => '7jZXl4gBUNl0CnaLEnfXbt',
					'refunded_amount' => 100,
					'scheme_transaction_id' => '123456789012345',
					'shipping_details' =>
						[
							'type' => 'shipping-details',
							'id' => '8724fd24-5489-4a5d-90fd-0604df7d3b83',
							'buyer_id' => '8724fd24-5489-4a5d-90fd-0604df7d3b83',
							'first_name' => 'John',
							'last_name' => 'Lunn',
							'email_address' => 'john@example.com',
							'phone_number' => '+1234567890',
							'address' =>
								[
									'city' => 'London',
									'country' => 'GB',
									'postal_code' => '789123',
									'state' => 'Greater London',
									'state_code' => 'GB-LND',
									'house_number_or_name' => '10',
									'line1' => '10 Oxford Street',
									'line2' => 'New Oxford Court',
									'organization' => 'Gr4vy',
								],
						],
					'statement_descriptor' =>
						[
							'name' => 'GR4VY',
							'description' => 'Card payment',
							'city' => 'London',
							'phone_number' => '+1234567890',
							'url' => 'www.gr4vy.com',
						],
					'status' => 'processing',
					'updated_at' => '2013-07-16T19:23:00.000+00:00',
					'voided_at' => '2013-07-16T19:23:00.000+00:00',
				],
			'recurring_payment_token' => '77a76f7e-d2de-4bbc-ada9-d6a0015e6bd5',
			'payment_method' => 'cc',
			'payment_submethod' => 'visa',
			'redirect_url' => 'https://api.example.app.gr4vy.com/payment-methods/ffc88ec9-e1ee-45ba-993d-b5902c3b2a8c/approve',
			'donor_details' =>
				[
					'first_name' => 'John',
					'last_name' => 'Lunn',
					'phone_number' => '+1234567890',
					'email_address' => 'john@example.com',
					'employer' => '',
					'external_identifier' => 'fe26475d-ec3e-4884-9553-f7356683f7f9',
					'address' =>
						[
							'address_line1' => '10 Oxford Street',
							'postal_code' => '789123',
							'state' => 'Greater London',
							'city' => 'London',
							'country' => 'GB',
						],
				],
		];
	}
}
