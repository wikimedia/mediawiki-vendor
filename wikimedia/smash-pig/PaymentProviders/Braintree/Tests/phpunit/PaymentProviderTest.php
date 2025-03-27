<?php

namespace SmashPig\PaymentProviders\Braintree\Tests;

use SmashPig\PaymentData\FinalStatus;
use SmashPig\PaymentProviders\Braintree\PaymentProvider;
use SmashPig\PaymentProviders\Braintree\ValidationErrorMapper;
use SmashPig\Tests\BaseSmashPigUnitTestCase;

/**
 * @group Braintree
 */
class PaymentProviderTest extends BaseSmashPigUnitTestCase {
	/**
	 * @var PHPUnit_Framework_MockObject_MockObject
	 */
	protected $api;

	public function setUp(): void {
		parent::setUp();
		$providerConfig = $this->setProviderConfiguration( 'braintree' );
		$this->api = $this->getMockBuilder( 'SmashPig\PaymentProviders\Braintree\Api' )
			->disableOriginalConstructor()
			->getMock();
		$providerConfig->overrideObjectInstance( 'api', $this->api );
	}

	public function testCreatePaymentSession() {
		$expectedSession = 'lkasjdlaksjdlaksjdlaksjdlaksjdlaksjdlaksjdlaksjdalksjdalksjdalskdj';
		$this->api->expects( $this->once() )
			->method( 'createClientToken' )
			->willReturn( [
				'data' => [ 'createClientToken' => [
					'clientToken' => $expectedSession
				] ],
				'extensions' => [
					'requestId' => '80f9dfbc-78fe-4d7e-89ad-03f46c9e50c8'
				]
			] );

		$provider = new PaymentProvider();
		$response = $provider->createPaymentSession();

		$this->assertEquals( $expectedSession, $response->getPaymentSession() );
	}

	/**
	 * Test confirming that the right param for the specified
	 * validation error's inputPath array is returned
	 */
	public function testValidationErrorMapper() {
		$validationError = ValidationErrorMapper::getValidationErrorField( [
			"input",
			"paymentMethodId"
		] );
		$this->assertEquals( 'payment_method', $validationError );
		$validationError = ValidationErrorMapper::getValidationErrorField( [
			"input",
			"transaction",
			"amount"
		] );
		$this->assertEquals( 'amount', $validationError );
		$validationError = ValidationErrorMapper::getValidationErrorField( [
			"input",
			"transaction",
			"mock"
		] );
		$this->assertEquals( 'general', $validationError );
	}

	/**
	 * Test refund payment with wrong gateway_tnx_id situations
	 * @return void
	 */
	public function testRefundPaymentNotFoundError() {
		$gateway_txn_id = "dHJhbnNhY3Rpb25fYXIxMTNuZzQ";
		$request = [
			"gateway_txn_id" => $gateway_txn_id,
			"order_id" => '30.1',
		];
		$this->api->expects( $this->once() )
			->method( 'refundPayment' )
			->willReturn( [
				'errors' => [
					[
						'message' => 'An object with this ID was not found.',
						'extensions' => [
							'errorClass' => 'NOT_FOUND',
							'inputPath' => [ "input", "transactionId" ]
						]
					]
				],
				'data' => [
					'refundTransaction' => null,
				],
				'extensions' => [
					'requestId' => '64513430-cd4b-4194-ad3c-bdbb8aae360d'
				]
			] );

		$provider = new PaymentProvider();
		$response = $provider->refundPayment( $request );
		$this->assertEquals( FinalStatus::FAILED, $response->getStatus() );
		$this->assertCount( 1, array_merge( $response->getValidationErrors(), $response->getErrors() ) );
		$this->assertEquals( '{"errorClass":"NOT_FOUND","inputPath":["input","transactionId"],"message":"An object with this ID was not found."}', $response->getErrors()[0]->getDebugMessage() );
	}

	/**
	 * Test refund payment with the gateway_tnx_id refunded validation error
	 * @return void
	 */
	public function testRefundPaymentRefundedError() {
		$gateway_txn_id = "dHJhbnNhY3Rpb25fOHdrNzI0NXk";
		$request = [
			"gateway_txn_id" => $gateway_txn_id,
			"order_id" => '81.1',
		];
		$this->api->expects( $this->once() )
			->method( 'refundPayment' )
			->willReturn( [
				'errors' => [
					[
						'message' => 'Transaction has already been fully refunded.',
						'extensions' => [
							'errorClass' => 'VALIDATION',
							'inputPath' => [ "input", "transactionId" ]
						]
					]
				],
				'data' => [
					'refundTransaction' => null,
				],
				'extensions' => [
					'requestId' => '73c86513-0b7a-4695-9385-19059ed82248'
				]
			] );

		$provider = new PaymentProvider();
		$response = $provider->refundPayment( $request );
		$this->assertEquals( FinalStatus::FAILED, $response->getStatus() );
		$this->assertCount( 1, array_merge( $response->getValidationErrors(), $response->getErrors() ) );
		$this->assertEquals( 'Transaction has already been fully refunded.', $response->getValidationErrors()[0]->getDebugMessage() );
	}

	/**
	 * Test refund payment with settlement_decline error
	 * @return void
	 */
	public function testRefundPaymentDeclineError() {
		$gateway_txn_id = "dHJhbnNhY3Rpb25fMncxOGdqenk";
		$request = [
			"gateway_txn_id" => $gateway_txn_id,
			"order_id" => '46.1',
		];
		$this->api->expects( $this->once() )
			->method( 'refundPayment' )
			->willReturn( [
				'data' => [
					'refundTransaction' => [
						'refund' => [
							'orderId' => '46.1',
							'status' => 'SETTLEMENT_DECLINED',
							'statusHistory' => [
								[
									'processorResponse' => [
										'message' => 'PayPal or Venmo account not configured to refund more than settled amount'
									]
								],
							]
						]
					],
				],
				'extensions' => [
					'requestId' => '73c86513-0b7a-4695-9385-19059ed82248'
				]
			] );

		$provider = new PaymentProvider();
		$response = $provider->refundPayment( $request );
		$this->assertEquals( FinalStatus::FAILED, $response->getStatus() );
		$this->assertEquals( 'SETTLEMENT_DECLINED', $response->getRawStatus() );
		$this->assertCount( 1, array_merge( $response->getValidationErrors(), $response->getErrors() ) );
		$this->assertEquals( '{"message":"PayPal or Venmo account not configured to refund more than settled amount"}', $response->getErrors()[0]->getDebugMessage() );
	}

	/**
	 * Test refund payment success
	 * @return void
	 */
	public function testRefundPaymentSuccess() {
		$gateway_txn_id = "dHJhbnNhY3Rpb25fOHdrNzI0NXk";
		$request = [
			"gateway_txn_id" => $gateway_txn_id,
			"order_id" => '81.1',
		];
		$this->api->expects( $this->once() )
			->method( 'refundPayment' )
			->willReturn( [
				'data' => [
					'refundTransaction' => [
						'refund' => [
							'orderId' => '81',
							'status' => 'SUBMITTED_FOR_SETTLEMENT'
						]
					],
				],
				'extensions' => [
					'requestId' => '23564f22-404d-4260-bd9c-af677dc90c6e'
				]
			] );

		$provider = new PaymentProvider();
		$response = $provider->refundPayment( $request );
		$this->assertEquals( FinalStatus::COMPLETE, $response->getStatus() );
		$this->assertCount( 0, array_merge( $response->getValidationErrors(), $response->getErrors() ) );
	}
}
