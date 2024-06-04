<?php

namespace SmashPig\PaymentProviders\Adyen\Tests\phpunit;

use SmashPig\PaymentProviders\Adyen\BankTransferPaymentProvider;
use SmashPig\PaymentProviders\Adyen\Tests\AdyenTestConfiguration;
use SmashPig\PaymentProviders\Adyen\Tests\BaseAdyenTestCase;

/**
 * @group Adyen
 */
class ACHPaymentProviderTest extends BaseAdyenTestCase {

	/**
	 * @var BankTransferPaymentProvider
	 */
	public $provider;

	public function setUp() : void {
		parent::setUp();
		$this->provider = $this->config->object( 'payment-provider/dd' );
	}

	public function testCreateACHPayment() {
		$ref = rand( 0, 100 );

		$this->mockApi->expects( $this->once() )
			->method( 'createACHDirectDebitPayment' )
			->willReturn( AdyenTestConfiguration::getSuccessfulACHResult( $ref ) );

		// test params
		$params = [
			'amount' => '85.00',
			'city' => 'Mountain View',
			'country' => 'US',
			'currency' => 'USD',
			'description' => 'Wikimedia Foundation',
			'email' => 'wfan@wikimedia.org',
			'full_name' => 'wfan test',
			'order_id' => '105.1',
			'postal_code' => '94043',
			'state_province' => 'CA',
			'street_address' => 'Amphitheatre Parkway',
			'payment_submethod' => 'ach',
			'encrypted_bank_account_number' => '123456789',
			'encrypted_bank_location_id' => '121000358',
			'supplemental_address_1' => '1600',
			'bank_account_type' => 'checking',
			'user_ip' => '172.21.0.1'
		];

		$approvePaymentResponse = $this->provider->createPayment( $params );
		$rawResponse = $approvePaymentResponse->getRawResponse();
		$this->assertEquals( 'Authorised', $approvePaymentResponse->getRawStatus() );
		$this->assertSame( 'NTLGLFS8C6PFWR82', $approvePaymentResponse->getGatewayTxnId() );
		$this->assertSame( $ref, $rawResponse[ 'merchantReference' ] );
		$this->assertTrue( $approvePaymentResponse->isSuccessful() );
	}

}
