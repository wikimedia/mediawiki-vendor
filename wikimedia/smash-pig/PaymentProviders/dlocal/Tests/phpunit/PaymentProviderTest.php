<?php

namespace SmashPig\PaymentProviders\dlocal\Tests\phpunit;

use SmashPig\Core\ApiException;
use SmashPig\PaymentData\FinalStatus;
use SmashPig\PaymentProviders\dlocal\Api;
use SmashPig\PaymentProviders\dlocal\PaymentProvider;
use SmashPig\Tests\BaseSmashPigUnitTestCase;

/**
 * @group Dlocal
 */
class PaymentProviderTest extends BaseSmashPigUnitTestCase {

	protected $api;

	public function setUp(): void {
		parent::setUp();
		$testingProviderConfiguration = $this->setProviderConfiguration( 'dlocal' );
		$this->api = $this->getMockBuilder( Api::class )
			->disableOriginalConstructor()
			->getMock();
		$testingProviderConfiguration->overrideObjectInstance( 'api', $this->api );
	}

	public function testGetLatestPaymentStatusPending(): void {
		$gatewayTxnId = 'D-2486-5bc9c596-f3b6-4b7c-bf3c-432276030cd9';
		$redirectUrl = 'https://sandbox.dlocal.com/collect/select_payment_method?id=M-ccb0c14e-b9df-4a4a-9ae3-7ad78895d6f3&xtid=CATH-ST-1675715007-109563397';
		$this->api->expects( $this->once() )
			->method( 'getPaymentStatus' )
			->with( $gatewayTxnId )
			->willReturn( [
				'id' => 'D-2486-5bc9c596-f3b6-4b7c-bf3c-432276030cd9',
				'status' => 'PENDING',
				'status_detail' => 'The payment is pending.',
				'status_code' => '100',
				'redirect_url' => $redirectUrl,
			] );

		$paymentProvider = new PaymentProvider();
		$params = [ 'gateway_txn_id' => $gatewayTxnId ];
		$paymentDetailResponse = $paymentProvider->getLatestPaymentStatus( $params );

		// TODO: do we wanna map the status detail and redirect url to the response?
		$this->assertEquals( $params['gateway_txn_id'], $paymentDetailResponse->getGatewayTxnId() );
		$this->assertEquals( FinalStatus::PENDING, $paymentDetailResponse->getStatus() );
	}

	public function testGetLatestPaymentStatusPaid(): void {
		$gatewayTxnId = 'D-2486-5bc9c596-f3b6-4b7c-bf3c-432276030cd9';
		$redirectUrl = 'https://sandbox.dlocal.com/collect/select_payment_method?id=M-ccb0c14e-b9df-4a4a-9ae3-7ad78895d6f3&xtid=CATH-ST-1675715007-109563397';
		$this->api->expects( $this->once() )
			->method( 'getPaymentStatus' )
			->with( $gatewayTxnId )
			->willReturn( [
				'id' => 'D-2486-5bc9c596-f3b6-4b7c-bf3c-432276030cd9',
				'status' => 'PAID',
				'status_detail' => 'The payment is paid.',
				'status_code' => '200',
				'redirect_url' => $redirectUrl,
			] );

		$paymentProvider = new PaymentProvider();
		$params = [ 'gateway_txn_id' => $gatewayTxnId ];
		$paymentDetailResponse = $paymentProvider->getLatestPaymentStatus( $params );

		// TODO: do we wanna map the status detail and redirect url to the response?
		$this->assertEquals( $params['gateway_txn_id'], $paymentDetailResponse->getGatewayTxnId() );
		$this->assertEquals( FinalStatus::COMPLETE, $paymentDetailResponse->getStatus() );
	}

	public function testGetLatestPaymentStatusThrowsExceptionOnPaymentIdNotFound(): void {
		$gatewayTxnId = 'D-INVALID-5bc9c596-f3b6-4b7c-bf3c-432276030cd9';
		$this->api->expects( $this->once() )
			->method( 'getPaymentStatus' )
			->with( $gatewayTxnId )
			->willThrowException(
				new ApiException( 'Response Error(404) {"code":4000,"message":"Payment not found"}' )
			);

		$this->expectException( ApiException::class );
		$this->expectExceptionMessage( 'Response Error(404) {"code":4000,"message":"Payment not found"}' );

		$params = [ 'gateway_txn_id' => $gatewayTxnId ];
		$paymentProvider = new PaymentProvider();
		$paymentProvider->getLatestPaymentStatus( $params );
	}

	public function testCancelPaymentSuccessful(): void {
		$gatewayTxnId = 'D-INVALID-5bc9c596-f3b6-4b7c-bf3c-432276030cd9';
		$this->api->expects( $this->once() )
			->method( 'cancelPayment' )
			->with( $gatewayTxnId )
			->willReturn(
				[
					"id" => $gatewayTxnId,
					"amount" => 100.00,
					"currency" => "BRL",
					"payment_method_id" => "VI",
					"payment_method_type" => "CARD",
					"payment_method_flow" => "DIRECT",
					"country" => "BR",
					"created_date" => "2023-02-15T19:05:27.000+0000",
					"approved_date" => "2023-02-15T19:05:27.000+0000",
					"status" => "CANCELLED",
					"status_detail" => "The payment was cancelled.",
					"status_code" => "200",
					"order_id" => "9134343.4",
					"description" => "Wikimedia 877 600 9454",
				]
			);
		$paymentProvider = new PaymentProvider();
		$response = $paymentProvider->cancelPayment( $gatewayTxnId );
		$this->assertTrue( $response->isSuccessful() );
		$this->assertEquals( $gatewayTxnId, $response->getGatewayTxnId() );
		$this->assertEquals( FinalStatus::CANCELLED, $response->getStatus() );
	}

	public function testGetPaymentDetail(): void {
		$gatewayTxnId = 'D-INVALID-5bc9c596-f3b6-4b7c-bf3c-432276030cd9';
		$this->api->expects( $this->once() )
			->method( 'getPaymentDetail' )
			->with( $gatewayTxnId )
			->willReturn(
				[
					"id" => $gatewayTxnId,
					"amount" => 1000.00,
					"currency" => "INR",
					"payment_method_id" => "IR",
					"payment_method_type" => "WALLET",
					"payment_method_flow" => "DIRECT",
					"country" => "IN",
					"created_date" => "2023-02-03T21:15:28.000+0000",
					"approved_date" => "2023-02-03T21:16:46.000+0000",
					"status" => "PAID",
					"status_detail" => "The payment was paid.",
					"status_code" => "200",
					"order_id" => "2bf0d4a4-6fdd-4e01-b4a6-2f329f457ed0",
					"description" => "test-wallet",
					"notification_url" => "http://conductor.sandbox.internal/robot-server/rest/generic/notification/new",
					"callback_url" => "https://paymentstest6.wmcloud.org/index.php?title=Special:AstroPayGatewayResult",
					"refunds" => [],
					"wallet" => [
						"token" => "09f3ce3c-04cb-4d0f-b8f5-2775d275c6bd",
						"username" => "ZXHhMuLL UlZdgMAV",
						"email" => "2wkz3wpxdigo-autest@dlocal.com",
					],
				]
			);
		$paymentProvider = new PaymentProvider();
		$response = $paymentProvider->getPaymentDetail( $gatewayTxnId );
		$this->assertTrue( $response->isSuccessful() );
		$this->assertEquals( $gatewayTxnId, $response->getGatewayTxnId() );
		$this->assertEquals( FinalStatus::COMPLETE, $response->getStatus() );
	}

	public function testCreatePaymentFromToken(): void {
		$params = [
			"amount" => "1500",
			"currency" => "INR",
			"country" => "IN",
			"payment_method_id" => "IR",
			"first_name" => "asdf",
			"last_name" => "asdf",
			"email" => "sample@samplemail.com",
			"fiscal_number" => "AAAAA9998C",
			"recurring_payment_token" => "aad328f2-61e8-4a89-a015-feef4d52ff2c",
			"order_id" => "839d446d-500b-43f0-b950-1689cfa0b630",
			"notification_url" => "https://wikimedia.notification/url",
		];
		$gatewayTxnId = "F-2486-7cdf7b27-5132-432e-9df8-3e2b2a8ca3a1";
		$this->api->expects( $this->once() )
			->method( 'createPaymentFromToken' )
			->with( $params )
			->willReturn(
				[
					"id" => $gatewayTxnId,
					"amount" => 1500.00,
					"currency" => "INR",
					"payment_method_id" => "IR",
					"payment_method_type" => "WALLET",
					"payment_method_flow" => "DIRECT",
					"country" => "IN",
					"created_date" => "2023-03-04T00:47:37.000+0000",
					"status" => "PENDING",
					"status_detail" => "The payment is pending.",
					"status_code" => "100",
					"order_id" => "839d446d-500b-43f0-b950-1689cfa0b630",
					"notification_url" => "https://wikimedia.notification/url",
					"recurring_info" => [
						"prenotify_approved" => false,
					],
				]
			);
		$paymentProvider = new PaymentProvider();
		$response = $paymentProvider->createPaymentFromToken( $params );
		$this->assertTrue( $response->isSuccessful() );
		$this->assertEquals( $gatewayTxnId, $response->getGatewayTxnId() );
		$this->assertEquals( FinalStatus::PENDING, $response->getStatus() );
	}

}
