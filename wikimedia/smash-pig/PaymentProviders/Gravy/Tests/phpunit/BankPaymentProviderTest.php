<?php
use SmashPig\PaymentProviders\Gravy\BankPaymentProvider;
use SmashPig\PaymentProviders\Gravy\Tests\BaseGravyTestCase;

/**
 * @group Gravy
 */
class BankPaymentProviderTest extends BaseGravyTestCase {
	/**
	 * @var BankPaymentProvider;
	 */
	public $provider;

	public function setUp() : void {
		parent::setUp();
		$this->provider = $this->config->object( 'payment-provider/dd' );
	}

	public function testSuccessfulCreatePaymentCreateDonor() {
		$responseBody = json_decode( file_get_contents( __DIR__ . '/../Data/trustly-create-transaction-success.json' ), true );
		$getDonorResponseBody = json_decode( file_get_contents( __DIR__ . '/../Data/list-buyer.json' ), true );
		$createDonorResponseBody = json_decode( file_get_contents( __DIR__ . '/../Data/create-buyer.json' ), true );

		$getDonorResponseBody['items'] = [];
		$this->mockApi->expects( $this->once() )
			->method( 'getDonor' )
			->willReturn( $getDonorResponseBody );
		$this->mockApi->expects( $this->once() )
			->method( 'createDonor' )
			->willReturn( $createDonorResponseBody );
		$this->mockApi->expects( $this->once() )
			->method( 'createPayment' )
			->willReturn( $responseBody );

		$params = $this->getCreateTrxnParams( $responseBody['amount'] );

		$response = $this->provider->createPayment( $params );

		$this->assertInstanceOf( '\SmashPig\PaymentProviders\Responses\CreatePaymentResponse',
			$response );
		$this->assertEquals( $responseBody['amount'] / 100, $response->getAmount() );
		$this->assertEquals( $responseBody['id'], $response->getGatewayTxnId() );
		$this->assertEquals( $responseBody['buyer']['billing_details']['first_name'], $response->getDonorDetails()->getFirstName() );
		$this->assertEquals( $responseBody['buyer']['billing_details']['last_name'], $response->getDonorDetails()->getLastName() );
		$this->assertEquals( $responseBody['buyer']['billing_details']['email_address'], $response->getDonorDetails()->getEmail() );
		$this->assertEquals( $responseBody['buyer']['id'], $response->getDonorDetails()->getCustomerId() );
		$this->assertEquals( $responseBody['buyer']['billing_details']['address']['line1'], $response->getDonorDetails()->getBillingAddress()->getStreetAddress() );
		$this->assertTrue( $response->requiresRedirect() );
		$this->assertEquals( $responseBody['payment_method']['approval_url'], $response->getRedirectUrl() );
		$this->assertTrue( $response->isSuccessful() );
		$this->assertEquals( "dd", $response->getPaymentMethod() );
		$this->assertEquals( "ach", $response->getPaymentSubmethod() );
	}

	private function getCreateTrxnParams( ?string $amount = '1299' ) {
		$params = [];
		$params['country'] = 'US';
		$params['currency'] = 'USD';
		$params['amount'] = $amount;
		$ct_id = mt_rand( 100000, 1000009 );
		$params['order_id'] = "$ct_id.1";
		$params['payment_method'] = "dd";
		$params['payment_submethod'] = "ach";

		$donorParams = $this->getCreateDonorParams();
		$params = array_merge( $params, $donorParams );

		return $params;
	}

	private function getCreateTrxnFromTokenParams( $amount ) {
		$params = $this->getCreateTrxnParams( $amount );

		unset( $params['gateway_session_id'] );

		$params['recurring_payment_token'] = "random_token";
		$params['processor_contact_id'] = "random_contact_id";

		return $params;
	}
}
