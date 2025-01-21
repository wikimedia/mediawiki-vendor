<?php

namespace SmashPig\PaymentProviders\Adyen\Tests\phpunit;

use SmashPig\PaymentProviders\Adyen\GooglePayPaymentProvider;
use SmashPig\PaymentProviders\Adyen\Tests\AdyenTestConfiguration;
use SmashPig\PaymentProviders\Adyen\Tests\BaseAdyenTestCase;

/**
 * @group Adyen
 */
class GooglePayPaymentProviderTest extends BaseAdyenTestCase {

	/**
	 * @var GooglePayPaymentProvider
	 */
	public $provider;

	public function setUp() : void {
		parent::setUp();
		$this->provider = $this->config->object( 'payment-provider/google' );
	}

	public function testCreateGooglePayment() {
		$ref = rand( 0, 100 );

		$this->mockApi->expects( $this->once() )
			->method( 'createGooglePayPayment' )
			->willReturn( AdyenTestConfiguration::getSuccessfulGoogleResult( $ref ) );

		// test params
		$params = [
			'amount' => '10.00',
			'city' => 'Mountain View',
			'country' => 'US',
			'currency' => 'USD',
			'description' => 'Wikimedia Foundation',
			'email' => 'dadedoyin@wikimedia.org',
			'order_id' => '105.1',
			'postal_code' => '94043',
			'state_province' => 'CA',
			'street_address' => '1600 Amphitheatre Parkway',
			'user_ip' => '172.21.0.1'
		];

		$approvePaymentResponse = $this->provider->createPayment( $params );
		$riskScores = $approvePaymentResponse->getRiskScores();
		$rawResponse = $approvePaymentResponse->getRawResponse();
		$this->assertEquals( 'Authorised', $approvePaymentResponse->getRawStatus() );
		$this->assertSame( '00000000000000AB', $approvePaymentResponse->getGatewayTxnId() );
		$this->assertSame( 50, $riskScores[ 'cvv' ] );
		$this->assertSame( 100, $riskScores[ 'avs' ] );
		$this->assertSame( $ref, $rawResponse[ 'merchantReference' ] );
		$this->assertTrue( $approvePaymentResponse->isSuccessful() );
	}

}
