<?php

namespace SmashPig\PaymentProviders\Gravy\Tests\phpunit;

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

	public function setUp() : void {
		parent::setUp();
		$this->provider = $this->config->object( 'payment-provider/cc' );
	}

	public function testSuccessfulCreateDonor() {
		$responseBody = json_decode( file_get_contents( __DIR__ . '/../Data/create-buyer.json' ), true );
		$this->mockApi->expects( $this->once() )
			->method( 'createDonor' )
			->willReturn( $responseBody );
		$params = $this->getCreateDonorParams();
		$response = $this->provider->createDonor( $params );

		$this->assertInstanceOf( '\SmashPig\PaymentProviders\Responses\PaymentDetailResponse',
			$response );
		$this->assertTrue( $response->isSuccessful() );
		$this->assertEquals( $responseBody['id'], $response->getDonorDetails()->getCustomerId() );
		$this->assertEquals( $params['first_name'], $response->getDonorDetails()->getFirstName() );
		$this->assertEquals( $params['last_name'], $response->getDonorDetails()->getLastName() );
		$this->assertEquals( $responseBody['billing_details']['address']['city'], $response->getDonorDetails()->getBillingAddress()->getCity() );
	}

	public function testValidationErrorCreateDonorBeforeApiCall() {
		$params = [
			'gateway_session_id' => 'random-session-id'
		];

		$response = $this->provider->createDonor( $params );

		$this->assertInstanceOf( '\SmashPig\PaymentProviders\Responses\PaymentDetailResponse',
			$response );
		$this->assertFalse( $response->isSuccessful() );
		$valErrors = $response->getValidationErrors();
		$errors = $response->getErrors();
		$this->assertCount( 3, $valErrors );
		$this->assertCount( 0, $errors );
	}

	public function testSuccessfulGetDonor() {
		$responseBody = json_decode( file_get_contents( __DIR__ . '/../Data/list-buyer.json' ), true );
		$this->mockApi->expects( $this->once() )
			->method( 'getDonor' )
			->willReturn( $responseBody );
		$params = $this->getCreateDonorParams();
		$response = $this->provider->getDonorRecord( $params );

		$this->assertInstanceOf( '\SmashPig\PaymentProviders\Responses\PaymentDetailResponse',
			$response );
		$this->assertTrue( $response->isSuccessful() );
		$donor = $responseBody['items'][0];
		$this->assertEquals( $donor['id'], $response->getDonorDetails()->getCustomerId() );
		$this->assertEquals( $params['first_name'], $response->getDonorDetails()->getFirstName() );
		$this->assertEquals( $params['last_name'], $response->getDonorDetails()->getLastName() );
		$this->assertEquals( $donor['billing_details']['address']['city'], $response->getDonorDetails()->getBillingAddress()->getCity() );
	}

	public function testValidationErrorGetDonorBeforeApiCall() {
		$params = [
			'gateway_session_id' => 'random-session-id'
		];

		$response = $this->provider->getDonorRecord( $params );

		$this->assertInstanceOf( '\SmashPig\PaymentProviders\Responses\PaymentDetailResponse',
			$response );
		$this->assertFalse( $response->isSuccessful() );
		$valErrors = $response->getValidationErrors();
		$errors = $response->getErrors();
		$this->assertCount( 1, $valErrors );
		$this->assertCount( 0, $errors );
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

	public function testGetLatestPaymentStatus() {
		$responseBody = json_decode( file_get_contents( __DIR__ . '/../Data/create-transaction.json' ), true );
		$params = [
			'gateway_txn_id' => 'random_txn_id'
		];

		$this->mockApi->expects( $this->once() )
			->method( 'getTransaction' )
			->with( $params )
			->willReturn( $responseBody );

		$response = $this->provider->getLatestPaymentStatus( $params );

		$this->assertInstanceOf( '\SmashPig\PaymentProviders\Responses\PaymentDetailResponse',
			$response );
		$this->assertEquals( $responseBody['amount'] / 100, $response->getAmount() );
		$this->assertEquals( $responseBody['id'], $response->getGatewayTxnId() );
		$this->assertEquals( $responseBody['buyer']['billing_details']['first_name'], $response->getDonorDetails()->getFirstName() );
		$this->assertEquals( $responseBody['buyer']['billing_details']['last_name'], $response->getDonorDetails()->getLastName() );
		$this->assertEquals( $responseBody['buyer']['billing_details']['email_address'], $response->getDonorDetails()->getEmail() );
		$this->assertEquals( $responseBody['buyer']['id'], $response->getDonorDetails()->getCustomerId() );
		$this->assertEquals( $responseBody['buyer']['billing_details']['address']['line1'], $response->getDonorDetails()->getBillingAddress()->getStreetAddress() );
		$this->assertEquals( explode( '-', $responseBody['payment_service']['payment_service_definition_id'] )[0], $response->getBackendProcessor() );
		$this->assertEquals( $responseBody['payment_service_transaction_id'], $response->getBackendProcessorTransactionId() );

		$this->assertTrue( $response->isSuccessful() );
	}

	public function testValidationErrorRefundBeforeApiCall() {
		$params = [
			'amount' => 1000
		];

		$response = $this->provider->refundPayment( $params );

		$this->assertInstanceOf( '\SmashPig\PaymentProviders\Responses\PaymentDetailResponse',
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

		$this->assertInstanceOf( '\SmashPig\PaymentProviders\Responses\PaymentDetailResponse',
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
}
