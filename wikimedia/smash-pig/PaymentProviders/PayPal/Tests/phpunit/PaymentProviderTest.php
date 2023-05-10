<?php

namespace SmashPig\PaymentProviders\PayPal\Tests;

use PHPUnit\Framework\MockObject\MockObject;
use SmashPig\Core\Context;
use SmashPig\Core\ProviderConfiguration;
use SmashPig\PaymentData\FinalStatus;
use SmashPig\Tests\BaseSmashPigUnitTestCase;
use SmashPig\Tests\TestingProviderConfiguration;

/**
 * @group PayPal
 */
class PaymentProviderTest extends BaseSmashPigUnitTestCase {

	/**
	 * @var ProviderConfiguration
	 */
	protected $config;

	/**
	 * @var MockObject
	 */
	protected $api;

	/**
	 * @var \SmashPig\PaymentProviders\PayPal\PaymentProvider
	 */
	protected $provider;

	public function setUp(): void {
		parent::setUp();
		$ctx = Context::get();
		$this->api = $this->createMock( 'SmashPig\PaymentProviders\PayPal\Api' );
		$this->config = TestingProviderConfiguration::createForProvider( 'paypal', $ctx->getGlobalConfiguration() );
		$this->config->overrideObjectInstance( 'api', $this->api );
		$ctx->setProviderConfiguration( $this->config );
		$this->provider = $this->config->object( 'payment-provider/paypal' );
	}

	/**
	 * Simulates a status lookup call for a user that has clicked the Complete Payment button
	 */
	public function testGetLatestPaymentStatus() {
		// set up expectations
		$testParams = [
			'gateway_session_id' => 'EC-TESTTOKEN12345678910'
		];
		$testApiResponse = $this->getTestData( 'GetLatestPaymentStatus.response' );
		parse_str( $testApiResponse, $parsedTestApiResponse );

		$this->api->expects( $this->once() )
			->method( 'getExpressCheckoutDetails' )
			->with( $this->equalTo( $testParams['gateway_session_id'] ) )
			->willReturn( $parsedTestApiResponse );

		// call the code
		$response = $this->provider->getLatestPaymentStatus( $testParams );

		// check the results
		$this->assertInstanceOf( 'SmashPig\PaymentProviders\Responses\PaymentDetailResponse', $response );
		$this->assertTrue( $response->isSuccessful() );
		$this->assertEquals( 25.00, $response->getAmount() );
		$this->assertEquals( 'USD', $response->getCurrency() );
		$this->assertEquals( "PaymentActionNotInitiated", $response->getRawStatus() );
		$this->assertEquals( "Success", $response->getRawResponse()['ACK'] );
		$this->assertTrue( $response->requiresApproval() );
		$this->assertEquals( 'FLJLQ2GV38E4Y', $response->getProcessorContactID() );
		$this->assertEquals( FinalStatus::PENDING_POKE, $response->getStatus() );
		$this->assertEquals( 'fr-tech+donor@wikimedia.org', $response->getDonorDetails()->getEmail() );
	}

	/**
	 * Simulates a status lookup call for a user that has NOT clicked the Complete Payment button
	 */
	public function testGetLatestPaymentStatusNotClicked() {
		// set up expectations
		$testParams = [
			'gateway_session_id' => 'EC-TESTTOKEN12345678910'
		];
		$testApiResponse = $this->getTestData( 'GetLatestPaymentStatusNotClicked.response' );
		parse_str( $testApiResponse, $parsedTestApiResponse );

		$this->api->expects( $this->once() )
			->method( 'getExpressCheckoutDetails' )
			->with( $this->equalTo( $testParams['gateway_session_id'] ) )
			->willReturn( $parsedTestApiResponse );

		// call the code
		$response = $this->provider->getLatestPaymentStatus( $testParams );

		// check the results
		$this->assertInstanceOf( 'SmashPig\PaymentProviders\Responses\PaymentDetailResponse', $response );
		$this->assertTrue( $response->isSuccessful() );
		$this->assertEquals( "PaymentActionNotInitiated", $response->getRawStatus() );
		$this->assertEquals( "Success", $response->getRawResponse()['ACK'] );
		$this->assertFalse( $response->requiresApproval() );
		$this->assertNull( $response->getProcessorContactID() );
		$this->assertEquals( FinalStatus::TIMEOUT, $response->getStatus() );
	}

	public function testGetLatestPaymentStatusWithError() {
		// set up expectations
		$testParams = [
			'gateway_session_id' => 'EC-3HX397483P386493S'
		];
		$testApiResponse = $this->getTestData( 'GetLatestPaymentStatusWithError.response' );
		parse_str( $testApiResponse, $parsedTestApiResponse );

		$this->api->expects( $this->once() )
			->method( 'getExpressCheckoutDetails' )
			->with( $this->equalTo( $testParams['gateway_session_id'] ) )
			->willReturn( $parsedTestApiResponse );

		// call the code
		$response = $this->provider->getLatestPaymentStatus( $testParams );

		// check the results
		$this->assertInstanceOf( 'SmashPig\PaymentProviders\Responses\PaymentDetailResponse', $response );
		$this->assertFalse( $response->isSuccessful() );
		$this->assertNull( $response->getRawStatus() );
		$this->assertNull( $response->getStatus() );
	}

	public function testCreateRecurringPaymentsProfileSampleApiCall() {
		// perform the test
		$testParams = [
			'order_id' => '15190.1',
			'amount' => '30.0',
			'currency' => 'USD',
			'email' => 'test_user@paypal.com',
			'gateway_session_id' => 'EC-74C37985WY171780F',
		];

		$testApiResponse = $this->getTestData( 'CreateRecurringPaymentsProfile.response' );
		parse_str( $testApiResponse, $parsedTestApiResponse );

		$this->api->expects( $this->once() )
			->method( 'createRecurringPaymentsProfile' )
			->with( $this->equalTo( $testParams ) )
			->willReturn( $parsedTestApiResponse );

		// call the code
		$response = $this->provider->createRecurringPaymentsProfile( $testParams );
		// check the results
		$this->assertInstanceOf( 'SmashPig\PaymentProviders\Responses\CreateRecurringPaymentsProfileResponse', $response );
		$this->assertTrue( $response->isSuccessful() );
		$this->assertEquals( "complete", $response->getStatus() );
		$this->assertEquals( "Success", $response->getRawStatus() );
		$this->assertEquals( "ActiveProfile", $response->getRawResponse()['PROFILESTATUS'] );
		$this->assertEquals( "Success", $response->getRawResponse()['ACK'] );
	}

	public function testApprovePayment() {
		// set up expectations
		$testParams = [
				'gateway_session_id' => 'EC-TESTTOKEN12345678910',
				'processor_contact_id' => 'FLJLQ2GV38E4Y',
				'order_id' => '15190.1',
				'amount' => '20.00',
				'currency' => 'USD',
				'description' => 'test DoExpressCheckouPayment',
		];

		$testApiResponse = $this->getTestData( 'DoExpressCheckoutPayment.response' );
		parse_str( $testApiResponse, $parsedTestApiResponse );

		$this->api->expects( $this->once() )
			->method( 'doExpressCheckoutPayment' )
			->with( $this->equalTo( $testParams ) )
			->willReturn( $parsedTestApiResponse );

		// call the code
		$response = $this->provider->approvePayment( $testParams );

		// check the results
		$this->assertInstanceOf( 'SmashPig\PaymentProviders\Responses\ApprovePaymentResponse', $response );
		$this->assertTrue( $response->isSuccessful() );
		$this->assertEquals( "complete", $response->getStatus() );
		$this->assertEquals( "Success", $response->getRawStatus() );
		$this->assertEquals( "Completed", $response->getRawResponse()['PAYMENTINFO_0_PAYMENTSTATUS'] );
		$this->assertEquals( "Success", $response->getRawResponse()['ACK'] );
	}

	public function testSetExpressCheckoutFail() {
		// set up expectations
		$testParams = [
			'amount' => '30.00',
			'currency' => 'ddd',
			'order_id' => '888.1',
			'is_recurring' => 1,
		];

		$testCreatePaymentSessionResponse = $this->getTestData( 'SetExpressCheckoutFail.response' );
		parse_str( $testCreatePaymentSessionResponse, $parsedTestCreatePaymentSessionResponse );

		$this->api->expects( $this->once() )
			->method( 'createPaymentSession' )
			->with( $this->equalTo( $testParams ) )
			->willReturn( $parsedTestCreatePaymentSessionResponse );

		// call the code
		$response = $this->provider->createPaymentSession( $testParams );

		// check the results
		$this->assertInstanceOf( 'SmashPig\PaymentProviders\Responses\CreatePaymentSessionResponse', $response );
		$this->assertFalse( $response->isSuccessful() );
		$this->assertEquals( "Failure", $response->getRawStatus() );
		$this->assertEquals( "Failure", $response->getRawResponse()['ACK'] );
	}

	public function testSetExpressCheckoutSuccess() {
		// set up expectations
		$testParams = [
			'amount' => '30.00',
			'currency' => 'USD',
			'order_id' => '888.1',
			'is_recurring' => 1,
		];

		$testCreatePaymentSessionResponse = $this->getTestData( 'SetExpressCheckoutSuccess.response' );
		parse_str( $testCreatePaymentSessionResponse, $parsedTestCreatePaymentSessionResponse );

		$this->api->expects( $this->once() )
			->method( 'createPaymentSession' )
			->with( $this->equalTo( $testParams ) )
			->willReturn( $parsedTestCreatePaymentSessionResponse );

		// call the code
		$response = $this->provider->createPaymentSession( $testParams );

		// check the results
		$this->assertInstanceOf( 'SmashPig\PaymentProviders\Responses\CreatePaymentSessionResponse', $response );
		$this->assertTrue( $response->isSuccessful() );
		$this->assertEquals( "EC-9J76236023054520Y", $response->getPaymentSession() );
		$this->assertEquals( "Success", $response->getRawStatus() );
		$this->assertEquals( "Success", $response->getRawResponse()['ACK'] );
	}

	private function getTestData( $testFileName ) {
		$testFileDir = __DIR__ . '/../Data/';
		$testFilePath = $testFileDir . $testFileName;
		if ( file_exists( $testFilePath ) ) {
			return file_get_contents( $testFilePath );
		}
	}

}
