<?php

namespace SmashPig\PaymentProviders\Gravy\Tests\phpunit;

use SmashPig\PaymentData\ErrorCode;
use SmashPig\PaymentData\FinalStatus;
use SmashPig\PaymentProviders\Gravy\PaymentProvider;
use SmashPig\PaymentProviders\Gravy\Tests\BaseGravyTestCase;

/**
 * @group Gravy
 */
class PaymentProviderTest extends BaseGravyTestCase {

	/**
	 * @var PaymentProvider
	 */
	public $provider;

	public function setUp(): void {
		parent::setUp();
		$this->provider = $this->config->object( 'payment-provider/cc' );
	}

	public function testValidationErrorDeletePaymentTokenApiCall() {
		$params = [
			'gateway_session_id' => 'random-session-id'
		];

		$response = $this->provider->deleteRecurringPaymentToken( $params );
		$this->assertFalse( $response );
	}

	public function testSuccessfulDeletePaymentTokenApiCall() {
		$params = [
			'recurring_payment_token' => 'random-payment-token'
		];

		$this->mockApi->expects( $this->once() )
			->method( 'deletePaymentToken' )
			->willReturn( [] );

		$response = $this->provider->deleteRecurringPaymentToken( $params );
		$this->assertTrue( $response );
	}

	public function testErrorDeletePaymentTokenApiCall() {
		$responseBody = json_decode( file_get_contents( __DIR__ . '/../Data/delete-token-error.json' ), true );
		$params = [
			'recurring_payment_token' => 'random-payment-token'
		];

		$this->mockApi->expects( $this->once() )
			->method( 'deletePaymentToken' )
			->willReturn( $responseBody );

		$response = $this->provider->deleteRecurringPaymentToken( $params );
		$this->assertFalse( $response );
	}

	/**
	 * Test that the `deleteRecurringPaymentToken` method correctly handles a cURL error string returned by the
	 * Gravy SDK client.
	 *
	 * This test ensures that:
	 * 1. The provider returns `false` when a cURL error occurs in the SDK client.
	 * 2. The API layer processes the cURL error string and converts it into an appropriate error array.
	 *
	 * The test verifies the entire flow from the provider to the API and the SDK client while simulating the cURL
	 * error scenario.
	 *
	 * @return void
	 */
	public function testDeletePaymentTokenHandlesCurlErrorString(): void {
		$paymentMethodId = 'test-payment-method-id';
		$params = [ 'recurring_payment_token' => $paymentMethodId ];
		$curlErrorMessage = 'cURL error 28: Operation timed out after 30000 milliseconds';

		// Mock the Gravy SDK client to return a cURL error string
		$mockGravyClient = $this->createMock( \Gr4vy\Gr4vyConfig::class );
		$mockGravyClient->expects( $this->exactly( 2 ) )
			->method( 'deletePaymentMethod' )
			->with( $paymentMethodId )
			->willReturn( $curlErrorMessage ); // SDK returns raw cURL error string

		// Create a real API instance and inject the mocked SDK client
		$api = $this->createApiInstance();
		$this->setMockGravyClient( $mockGravyClient, $api );

		// Test the complete flow: Provider -> API -> SDK (returns cURL error) -> bubbles back up
		$providerResult = $this->provider->deleteRecurringPaymentToken( $params );

		// Verify the provider correctly handles the error and returns false
		$this->assertFalse( $providerResult, 'Provider should return false when encountering cURL error' );

		// Also verify the API layer converts the string error correctly
		$apiResult = $api->deletePaymentToken( [ 'payment_method_id' => $paymentMethodId ] );
		$this->assertArrayHasKey( 'type', $apiResult, 'API should convert cURL error string to error array' );
		$this->assertArrayHasKey( 'message', $apiResult, 'API should convert cURL error string to error array' );
		$this->assertEquals( 'error', $apiResult['type'], 'API should return error type' );
		$this->assertEquals( 'deletePaymentMethod response: (test-payment-method-id) ' . $curlErrorMessage, $apiResult['message'], 'API should preserve the original cURL error message' );
	}

	public function testApiErrorDeletePaymentTokenApiCall() {
		$responseBody = json_decode( file_get_contents( __DIR__ . '/../Data/delete-token-api-error.json' ), true );
		$params = [
			'recurring_payment_token' => 'random-payment-token'
		];

		$this->mockApi->expects( $this->once() )
			->method( 'deletePaymentToken' )
			->willReturn( $responseBody );

		$response = $this->provider->deleteRecurringPaymentToken( $params );
		$this->assertFalse( $response );
	}

	public function testGetLatestPaymentStatus(): void {
		$responseBody = json_decode( file_get_contents( __DIR__ . '/../Data/create-transaction.json' ), true );
		$params = [
			'gateway_txn_id' => 'random_txn_id'
		];

		$this->mockApi->expects( $this->once() )
			->method( 'getTransaction' )
			->with( $params )
			->willReturn( $responseBody );

		$response = $this->provider->getLatestPaymentStatus( $params );

		$this->assertInstanceOf( '\SmashPig\PaymentProviders\Responses\PaymentProviderExtendedResponse',
			$response );
		$this->assertEquals( $responseBody['amount'] / 100, $response->getAmount() );
		$this->assertEquals( $responseBody['id'], $response->getGatewayTxnId() );
		$this->assertEquals( $responseBody['reconciliation_id'], $response->getPaymentOrchestratorReconciliationId() );
		$this->assertEquals( $responseBody['buyer']['billing_details']['first_name'], $response->getDonorDetails()->getFirstName() );
		$this->assertEquals( $responseBody['buyer']['billing_details']['last_name'], $response->getDonorDetails()->getLastName() );
		$this->assertEquals( $responseBody['buyer']['billing_details']['email_address'], $response->getDonorDetails()->getEmail() );
		$this->assertEquals( $responseBody['buyer']['id'], $response->getDonorDetails()->getCustomerId() );
		$this->assertEquals( $responseBody['buyer']['billing_details']['address']['line1'], $response->getDonorDetails()->getBillingAddress()->getStreetAddress() );
		$this->assertEquals( explode( '-', $responseBody['payment_service']['payment_service_definition_id'] )[0], $response->getBackendProcessor() );
		$this->assertEquals( $responseBody['payment_service_transaction_id'], $response->getBackendProcessorTransactionId() );

		$this->assertTrue( $response->isSuccessful() );
	}

	public function testGetLatestPaymentStatusCancelledApproval(): void {
		$responseBody = json_decode( file_get_contents( __DIR__ . '/../Data/buyer-cancel-approval-from-redirect.json' ), true );
		$params = [
			'gateway_txn_id' => 'random_txn_id'
		];

		$this->mockApi->expects( $this->once() )
			->method( 'getTransaction' )
			->with( $params )
			->willReturn( $responseBody );

		$response = $this->provider->getLatestPaymentStatus( $params );

		$this->assertInstanceOf( '\SmashPig\PaymentProviders\Responses\PaymentProviderExtendedResponse',
			$response );

		$this->assertFalse( $response->isSuccessful() );
		$this->assertSame( ErrorCode::CANCELLED_BY_DONOR, $response->getNormalizedResponse()['code'] );
		$this->assertSame( FinalStatus::CANCELLED, $response->getStatus() );
	}

	public function testValidationErrorRefundBeforeApiCall() {
		$params = [
			'amount' => 1000
		];

		$response = $this->provider->refundPayment( $params );

		$this->assertInstanceOf( '\SmashPig\PaymentProviders\Responses\PaymentProviderExtendedResponse',
			$response );
		$this->assertFalse( $response->isSuccessful() );
		$valErrors = $response->getValidationErrors();
		$errors = $response->getErrors();
		// 2 - missing currency and gateway_txn_id
		$this->assertCount( 2, $valErrors );
		$this->assertCount( 0, $errors );
	}

	public function testApiErrorRefundApiCall() {
		$params = [
			'gateway_txn_id' => 'random-id'
		];
		$responseBody = json_decode( file_get_contents( __DIR__ . '/../Data/refund-api-error.json' ), true );

		$this->mockApi->expects( $this->once() )
		->method( 'refundTransaction' )
		->willReturn( $responseBody );

		$response = $this->provider->refundPayment( $params );

		$this->assertInstanceOf( '\SmashPig\PaymentProviders\Responses\PaymentProviderExtendedResponse',
			$response );
		$this->assertFalse( $response->isSuccessful() );
		$errors = $response->getErrors();

		$this->assertCount( 1, $errors );
	}

	public function testSuccessfulRefundPayment() {
		$responseBody = json_decode( file_get_contents( __DIR__ . '/../Data/successful-refund.json' ), true );
		$params = [
			'gateway_txn_id' => $responseBody['transaction_id'],
			'amount' => $responseBody['amount'] / 100,
			'currency' => $responseBody['currency']
		];
		$this->mockApi->expects( $this->once() )
			->method( 'refundTransaction' )
			->with( [
				'gateway_txn_id' => $responseBody['transaction_id'],
				'body' => [
					'amount' => $responseBody['amount'],
					'reason' => 'Refunded due to user request'
				]
			] )
			->willReturn( $responseBody );

		$response = $this->provider->refundPayment( $params );

		$this->assertInstanceOf( '\SmashPig\PaymentProviders\Responses\RefundPaymentResponse',
			$response );
		$this->assertEquals( $responseBody['amount'] / 100, $response->getAmount() );
		$this->assertEquals( $responseBody['id'], $response->getGatewayRefundId() );
		$this->assertEquals( $responseBody['transaction_id'], $response->getGatewayParentId() );
		$this->assertEquals( $responseBody['currency'], $response->getCurrency() );
		$this->assertEquals( $responseBody['reason'], $response->getReason() );
		$this->assertTrue( $response->isSuccessful() );
	}

	public function testGetSuccessfulRefundPayment() {
		$responseBody = json_decode( file_get_contents( __DIR__ . '/../Data/successful-refund.json' ), true );
		$params = [
			'gateway_refund_id' => $responseBody['id']
		];
		$this->mockApi->expects( $this->once() )
			->method( 'getRefund' )
			->with( $params )
			->willReturn( $responseBody );

		$response = $this->provider->getRefundDetails( $params );

		$this->assertInstanceOf( '\SmashPig\PaymentProviders\Responses\RefundPaymentResponse',
			$response );
		$this->assertEquals( $responseBody['amount'] / 100, $response->getAmount() );
		$this->assertEquals( $responseBody['id'], $response->getGatewayRefundId() );
		$this->assertEquals( $responseBody['transaction_id'], $response->getGatewayParentId() );
		$this->assertEquals( $responseBody['currency'], $response->getCurrency() );
		$this->assertEquals( $responseBody['reason'], $response->getReason() );
		$this->assertTrue( $response->isSuccessful() );
	}

	public function testGetDownloadReportUrlSuccessful() {
		$responseBody = json_decode( file_get_contents( __DIR__ . '/../Data/generate-report-url-successful.json' ), true );
		$params = [
			'report_execution_id' => 'random-exec-id',
			'report_id' => 'random-id',
		];
		$this->mockApi->expects( $this->once() )
			->method( 'generateReportDownloadUrl' )
			->with( $params )
			->willReturn( $responseBody );

		$response = $this->provider->generateReportDownloadUrl( $params );

		$this->assertInstanceOf( '\SmashPig\PaymentProviders\Gravy\Responses\ReportResponse',
			$response );
		$this->assertEquals( $responseBody['url'], $response->getReportUrl() );
		$this->assertTrue( $response->isSuccessful() );
	}

	public function testApiErrorGetDownloadReportUrl() {
		$responseBody = json_decode( file_get_contents( __DIR__ . '/../Data/generate-report-url-fail.json' ), true );
		$params = [
			'report_execution_id' => 'random-id'
		];
		$this->mockApi->expects( $this->once() )
			->method( 'getReportExecutionDetails' )
			->willReturn( $responseBody );

		$response = $this->provider->getReportExecutionDetails( $params );

		$this->assertInstanceOf( '\SmashPig\PaymentProviders\Gravy\Responses\ReportResponse',
			$response );
		$this->assertFalse( $response->isSuccessful() );
		$errors = $response->getErrors();

		$this->assertCount( 1, $errors );
	}

	public function testGetSuccessfulReportExecution() {
		$responseBody = json_decode( file_get_contents( __DIR__ . '/../Data/report-execution-successful.json' ), true );
		$params = [
			'report_execution_id' => $responseBody['id']
		];
		$this->mockApi->expects( $this->once() )
			->method( 'getReportExecutionDetails' )
			->with( $params )
			->willReturn( $responseBody );

		$response = $this->provider->getReportExecutionDetails( $params );

		$this->assertInstanceOf( '\SmashPig\PaymentProviders\Gravy\Responses\ReportResponse',
			$response );
		$this->assertEquals( $responseBody['id'], $response->getReportExecutionId() );
		$this->assertEquals( $responseBody['report']['id'], $response->getReportId() );
		$this->assertTrue( $response->isSuccessful() );
	}

	public function testApiErrorReportExecution() {
		$responseBody = json_decode( file_get_contents( __DIR__ . '/../Data/report-execution-fail.json' ), true );
		$params = [
			'report_execution_id' => 'random-id'
		];
		$this->mockApi->expects( $this->once() )
			->method( 'getReportExecutionDetails' )
			->willReturn( $responseBody );

		$response = $this->provider->getReportExecutionDetails( $params );

		$this->assertInstanceOf( '\SmashPig\PaymentProviders\Gravy\Responses\ReportResponse',
			$response );
		$this->assertFalse( $response->isSuccessful() );
		$errors = $response->getErrors();

		$this->assertCount( 1, $errors );
	}

	public function testSuccessfulApprovePayment() {
		$responseBody = json_decode( file_get_contents( __DIR__ . '/../Data/capture-transaction.json' ), true );
		$params = $this->getApproveTrxnParams();

		$this->mockApi->expects( $this->once() )
			->method( 'approvePayment' )
			->with( $params['gateway_txn_id'], [ 'amount' => 1299 ] )
			->willReturn( $responseBody );

		$response = $this->provider->approvePayment( $params );

		$this->assertInstanceOf( '\SmashPig\PaymentProviders\Responses\ApprovePaymentResponse',
			$response );

		$this->assertTrue( $response->isSuccessful() );
		$this->assertEquals( FinalStatus::PENDING, $response->getStatus() );
		$this->assertEquals( $responseBody['id'], $response->getGatewayTxnId() );
		$this->assertEquals( $responseBody['payment_service_transaction_id'], $response->getBackendProcessorTransactionId() );
		$this->assertEquals( $responseBody['reconciliation_id'], $response->getPaymentOrchestratorReconciliationId() );
	}

	private function getApproveTrxnParams( $amount = '12.99' ) {
		return [
			'amount' => $amount,
			'currency' => 'USD',
			'gateway_txn_id' => 'random-id'
		];
	}
}
