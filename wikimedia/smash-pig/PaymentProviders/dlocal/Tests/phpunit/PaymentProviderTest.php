<?php

namespace SmashPig\PaymentProviders\dlocal\Tests\phpunit;

use SmashPig\Core\ApiException;
use SmashPig\PaymentData\FinalStatus;
use SmashPig\PaymentProviders\dlocal\Api;
use SmashPig\PaymentProviders\dlocal\PaymentProvider;
use SmashPig\Tests\BaseSmashPigUnitTestCase;

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
}
