<?php

namespace SmashPig\PaymentProviders\Gravy\Tests\phpunit;

use SmashPig\PaymentProviders\Gravy\Errors\ErrorHelper;
use SmashPig\PaymentProviders\Gravy\Tests\BaseGravyTestCase;

/**
 * @group Gravy
 */
class ErrorHelperTest extends BaseGravyTestCase {

	public function testBuildTrackableErrorFromResponseWithCompleteData(): void {
		$errorCode = 'cancelled_buyer_approval';
		$errorType = 'error_code_present';
		$response = [
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

		$result = ErrorHelper::buildTrackableErrorFromResponse( $errorCode, $errorType, $response );

		// Check core fields
		$this->assertEquals( 'cancelled_buyer_approval', $result['error_code'] );
		$this->assertEquals( 'error_code_present', $result['error_type'] );
		$this->assertEquals( 'f010a662-757e-4881-bad2-65feb1762a1e', $result['id'] );
		$this->assertSame( '232515486.1', $result['external_identifier'] );
		$this->assertEquals( 'USD', $result['currency'] );
		$this->assertEquals( 10400, $result['amount'] );
		$this->assertIsArray( $result['payment_method'] );
		$this->assertEquals( 'trustly', $result['payment_method']['method'] );
		$this->assertEquals( 'trustly', $result['backend_processor'] );

		// Check computed fields
		$this->assertArrayHasKey( 'sample_transaction_id', $result );
		$this->assertArrayHasKey( 'sample_data', $result );
		$this->assertEquals( 'f010a662-757e-4881-bad2-65feb1762a1e', $result['sample_transaction_id'] );
	}

	public function testBuildTrackableErrorFromResponseWithMinimalData(): void {
		$errorCode = 'unauthorized';
		$errorType = 'error_response_type';
		$response = $this->loadTestData( 'refund-api-error.json' );

		$result = ErrorHelper::buildTrackableErrorFromResponse( $errorCode, $errorType, $response );

		// Check core fields
		$this->assertEquals( 'unauthorized', $result['error_code'] );
		$this->assertEquals( 'error_response_type', $result['error_type'] );

		// Check computed fields exist
		$this->assertArrayHasKey( 'sample_transaction_id', $result );
		$this->assertArrayHasKey( 'sample_data', $result );
	}

	public function testBuildTrackableErrorFromResponseWithPartialData(): void {
		$errorCode = 'failed';
		$errorType = 'failed_intent';
		$response = $this->loadTestData( 'trustly-create-transaction-failed.json' );

		$result = ErrorHelper::buildTrackableErrorFromResponse( $errorCode, $errorType, $response );

		// Check core fields - error_code now contains the input directly
		$this->assertEquals( $errorCode, $result['error_code'] );
		$this->assertEquals( $errorType, $result['error_type'] );
		$this->assertEquals( '943bec45-7cab-4555-8ea1-def34c34fae9', $result['id'] );
		$this->assertSame( '417.2', $result['external_identifier'] );
		$this->assertEquals( 'USD', $result['currency'] );
		$this->assertEquals( 1223, $result['amount'] );
		$this->assertIsArray( $result['payment_method'] );
		$this->assertEquals( 'trustly', $result['payment_method']['method'] );
		$this->assertEquals( 'trustly', $result['backend_processor'] );

		// Check computed fields exist
		$this->assertArrayHasKey( 'sample_transaction_id', $result );
		$this->assertArrayHasKey( 'sample_data', $result );
	}

	public function testBuildTrackableErrorFromResponseWith3DSecureError(): void {
		$errorCode = 'error';
		$errorType = '3d_secure_error';
		$response = $this->loadTestData( 'create-transaction-3dsecure-error.json' );

		$result = ErrorHelper::buildTrackableErrorFromResponse( $errorCode, $errorType, $response );

		// Check core fields
		$this->assertEquals( $errorCode, $result['error_code'] );
		$this->assertEquals( $errorType, $result['error_type'] );
		$this->assertEquals( '61df177c-76b3-4f0c-80fb-d1ad53764c91', $result['id'] );
		$this->assertSame( '166.3', $result['external_identifier'] );
		$this->assertEquals( 'USD', $result['currency'] );
		$this->assertEquals( 1000, $result['amount'] );
		$this->assertIsArray( $result['payment_method'] );
		$this->assertEquals( 'card', $result['payment_method']['method'] );
		$this->assertEquals( 'adyen', $result['backend_processor'] );

		// Check computed fields exist
		$this->assertArrayHasKey( 'sample_transaction_id', $result );
		$this->assertArrayHasKey( 'sample_data', $result );
	}

	public function testBuildTrackableErrorFromResponseWithFailedPaymentStatus(): void {
		$errorCode = 'authorization_declined';
		$errorType = 'failed_payment_status';
		$response = $this->loadTestData( 'create-transaction-3dsecure-error.json' );

		$result = ErrorHelper::buildTrackableErrorFromResponse( $errorCode, $errorType, $response );

		// Check core fields
		$this->assertEquals( $errorCode, $result['error_code'] );
		$this->assertEquals( $errorType, $result['error_type'] );
		$this->assertEquals( '61df177c-76b3-4f0c-80fb-d1ad53764c91', $result['id'] );
		$this->assertSame( '166.3', $result['external_identifier'] );
		$this->assertEquals( 'USD', $result['currency'] );
		$this->assertEquals( 1000, $result['amount'] );
		$this->assertIsArray( $result['payment_method'] );
		$this->assertEquals( 'card', $result['payment_method']['method'] );
		$this->assertEquals( 'adyen', $result['backend_processor'] );

		// Check computed fields exist
		$this->assertArrayHasKey( 'sample_transaction_id', $result );
		$this->assertArrayHasKey( 'sample_data', $result );
	}

	/**
	 * Helper method to load JSON test data
	 */
	private function loadTestData( string $filename ): array {
		$filePath = __DIR__ . '/../Data/' . $filename;
		$jsonContent = file_get_contents( $filePath );
		return json_decode( $jsonContent, true );
	}
}
