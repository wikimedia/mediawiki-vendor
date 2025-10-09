<?php

namespace SmashPig\PaymentProviders\Gravy\Tests\phpunit;

use SmashPig\PaymentProviders\Gravy\Errors\ErrorHelper;
use SmashPig\PaymentProviders\Gravy\Tests\BaseGravyTestCase;

/**
 * @group Gravy
 */
class ErrorHelperTest extends BaseGravyTestCase {

	public function testBuildTrackableErrorFromResponseWithCompleteData(): void {
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
		$testErrorCode = 'cancelled_buyer_approval';
		$testErrorType = 'error_code_present';
		$result = ErrorHelper::buildTrackableError( $testErrorCode, $testErrorType, $testResponse );

		// Check core fields
		$this->assertEquals( 'cancelled_buyer_approval', $result['error_code'] );
		$this->assertEquals( 'error_code_present', $result['error_type'] );
		$this->assertSame( '232515486.1', $result['external_identifier'] );
		$this->assertEquals( 'f010a662-757e-4881-bad2-65feb1762a1e', $result['sample_transaction_id'] );
		$this->assertEquals( ' - Trustly, 232515486.1, USD 10400.00, via trustly, from US', $result['sample_transaction_summary'] );
		$this->assertEquals( 'USD', $result['currency'] );
		$this->assertEquals( 10400, $result['amount'] );
	}

	public function testBuildTrackableErrorFromResponseWithMinimalData(): void {
		$testResponse = $this->loadTestData( 'refund-api-error.json' );
		$testErrorCode = 'unauthorized';
		$testErrorType = 'error_response_type';
		$result = ErrorHelper::buildTrackableError( $testErrorCode, $testErrorType, $testResponse );

		// Check core fields
		$this->assertEquals( 'unauthorized', $result['error_code'] );
		$this->assertEquals( 'error_response_type', $result['error_type'] );
		$this->assertNull( $result['sample_transaction_id'] );
		$this->assertNull( $result['sample_transaction_summary'] );
	}

	public function testBuildTrackableErrorFromResponseWithPartialData(): void {
		$errorCode = 'failed';
		$errorType = 'failed_intent';
		$response = $this->loadTestData( 'trustly-create-transaction-failed.json' );

		$result = ErrorHelper::buildTrackableError( $errorCode, $errorType, $response );

		// Check core fields
		$this->assertEquals( $errorCode, $result['error_code'] );
		$this->assertEquals( $errorType, $result['error_type'] );
		$this->assertEquals( '943bec45-7cab-4555-8ea1-def34c34fae9', $result['sample_transaction_id'] );
		$this->assertEquals( ' - Trustly, 417.2, USD 1223.00, via trustly, from US', $result['sample_transaction_summary'] );
		$this->assertSame( '417.2', $result['external_identifier'] );
		$this->assertEquals( 'USD', $result['currency'] );
		$this->assertEquals( 1223, $result['amount'] );
	}

	public function testBuildTrackableErrorFromResponseWith3DSecureError(): void {
		$errorCode = 'error';
		$errorType = '3d_secure_error';
		$response = $this->loadTestData( 'create-transaction-3dsecure-error.json' );

		$result = ErrorHelper::buildTrackableError( $errorCode, $errorType, $response );

		// Check core fields
		$this->assertEquals( $errorCode, $result['error_code'] );
		$this->assertEquals( $errorType, $result['error_type'] );
		$this->assertEquals( '61df177c-76b3-4f0c-80fb-d1ad53764c91', $result['sample_transaction_id'] );
		$this->assertEquals( ' - Adyen, 166.3, USD 1000.00, via card, from US', $result['sample_transaction_summary'] );
		$this->assertSame( '166.3', $result['external_identifier'] );
		$this->assertEquals( 'USD', $result['currency'] );
		$this->assertEquals( 1000, $result['amount'] );
		$this->assertIsArray( $result['payment_method'] );
	}

	public function testBuildTrackableErrorFromResponseWithFailedPaymentStatus(): void {
		$errorCode = 'authorization_declined';
		$errorType = 'failed_payment_status';
		$response = $this->loadTestData( 'create-transaction-3dsecure-error.json' );

		$result = ErrorHelper::buildTrackableError( $errorCode, $errorType, $response );

		// Check core fields
		$this->assertEquals( $errorCode, $result['error_code'] );
		$this->assertEquals( $errorType, $result['error_type'] );
		$this->assertEquals( '61df177c-76b3-4f0c-80fb-d1ad53764c91', $result['sample_transaction_id'] );
		$this->assertEquals( ' - Adyen, 166.3, USD 1000.00, via card, from US', $result['sample_transaction_summary'] );
		$this->assertSame( '166.3', $result['external_identifier'] );
		$this->assertEquals( 'USD', $result['currency'] );
		$this->assertEquals( 1000, $result['amount'] );
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
