<?php

namespace SmashPig\PaymentProviders\Braintree\Tests;

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

	public function setUp() : void {
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
		$this->assertEquals( $validationError, 'payment_method' );
		$validationError = ValidationErrorMapper::getValidationErrorField( [
			"input",
			"transaction",
			"amount"
		] );
		$this->assertEquals( $validationError, 'amount' );
		$validationError = ValidationErrorMapper::getValidationErrorField( [
			"input",
			"transaction",
			"mock"
		] );
		$this->assertEquals( $validationError, 'general' );
	}
}
