<?php

namespace SmashPig\PaymentProviders\Ingenico\Tests;

use SmashPig\PaymentData\FinalStatus;
use SmashPig\PaymentData\RecurringModel;
use SmashPig\PaymentProviders\Ingenico\HostedCheckoutProvider;
use SmashPig\Tests\BaseSmashPigUnitTestCase;

/**
 * @group Ingenico
 */
class HostedCheckoutProviderTest extends BaseSmashPigUnitTestCase {
	/**
	 * @var HostedCheckoutProvider
	 */
	protected $provider;

	public function setUp(): void {
		parent::setUp();
		$this->setProviderConfiguration( 'ingenico' );
		$this->provider = new HostedCheckoutProvider( [ 'subdomain' => 'payments.test' ] );
	}

	public function testCreateHostedPayment() {
		$params = [
			[
			"hostedCheckoutSpecificInput" => [
				"locale" => "en_GB",
				"variant" => "testVariant"
				],
			],
			"order" => [
				"amountOfMoney" => [
					"currencyCode" => "USD",
					"amount" => 2345
				],
				"customer" => [
					"billingAddress" => [
						"countryCode" => "US"
					]
				]
			]
		];
		$expectedResponse = [
			"partialRedirectUrl" => "pay1.secured-by-ingenico.com/pay8915-53ebca407e6b4a1dbd086aad4f10354d:8915-28e5b79c889641c8ba770f1ba576c1fe:9798f4c44ac6406e8288494332d1daa0",
			"hostedCheckoutId" => "8915-28e5b79c889641c8ba770f1ba576c1fe",
			"RETURNMAC" => "f5b66cf9-c64c-4c8d-8171-b47205c89a56"
		];
		$this->setUpResponse( __DIR__ . '/../Data/newHostedCheckout.response', 200 );
		$this->curlWrapper->expects( $this->once() )
			->method( 'execute' )->with(
				'https://eu.sandbox.api-ingenico.com/v1/1234/hostedcheckouts',
				'POST'
			);
		$response = $this->provider->createHostedPayment( $params );
		$this->assertEquals( $expectedResponse, $response );
	}

	public function testGetHostedPaymentUrl() {
		$partialRedirectUrl = "pay1.secured-by-ingenico.com/pay8915-53ebca407e6b4a1dbd086aad4f10354d:8915-28e5b79c889641c8ba770f1ba576c1fe";
		$hostedPaymentUrl = $this->provider->getHostedPaymentUrl( $partialRedirectUrl );
		$expectedUrl = 'https://payments.test.' . $partialRedirectUrl;
		$this->assertEquals( $expectedUrl, $hostedPaymentUrl );
	}

	public function testGetLatestPaymentStatus() {
		$hostedPaymentId = '8915-28e5b79c889641c8ba770f1ba576c1fe';
		$this->setUpResponse( __DIR__ . "/../Data/hostedPaymentStatus.response", 200 );
		$this->curlWrapper->expects( $this->once() )
			->method( 'execute' )->with(
				"https://eu.sandbox.api-ingenico.com/v1/1234/hostedcheckouts/$hostedPaymentId",
				'GET'
			);
		$response = $this->provider->getLatestPaymentStatus( [ 'gateway_session_id' => $hostedPaymentId ] );
		$rawResponse = $response->getRawResponse();
		$this->assertEquals( 'PAYMENT_CREATED', $rawResponse['status'] );
		// checking the PaymentProviderExtendedResponse
		$this->assertEquals( 'PENDING_APPROVAL', $response->getRawStatus() );
		$this->assertEquals( 'pending-poke', $response->getStatus() );
		$this->assertTrue( $response->isSuccessful() );
		$this->assertEquals( 23.45, $response->getAmount() );
		$this->assertEquals( 'USD', $response->getCurrency() );
		$this->assertEquals( 'visa', $response->getPaymentSubmethod() );
		$this->assertSame( '000000891566072501680000200001', $response->getGatewayTxnId() );
		$this->assertEquals( [ 'avs' => 25, 'cvv' => 0 ], $response->getRiskScores() );
		$this->assertEquals( "Testy McTesterson", $response->getDonorDetails()->getFullName() );
	}

	/**
	 * Test that we prefer the explicitly specified initialSchemeTransactionId
	 * @throws \SmashPig\Core\ApiException
	 */
	public function testGetLatestPaymentStatusWithInitialSchemeTransactionId() {
		$this->setUpResponse( __DIR__ . "/../Data/hostedPaymentStatusWithInitialSchemeId.response", 200 );
		$response = $this->provider->getLatestPaymentStatus( [ 'gateway_session_id' => '8915-28e5b79c889641c8ba770f1ba576c1fe' ] );
		$this->assertEquals( "asdf1234asdf1234Sdasdf1234", $response->getInitialSchemeTransactionId() );
	}

	/**
	 * Test that we map the fallback schemeTransactionId
	 * @throws \SmashPig\Core\ApiException
	 */
	public function testGetLatestPaymentStatusWithSchemeTransactionId() {
		$this->setUpResponse( __DIR__ . "/../Data/hostedPaymentStatusWithSchemeId.response", 200 );
		$response = $this->provider->getLatestPaymentStatus( [ 'gateway_session_id' => '8915-28e5b79c889641c8ba770f1ba576c1fe' ] );
		$this->assertEquals( "lkjh0987lkjh0987lkjh0987", $response->getInitialSchemeTransactionId() );
	}

	/**
	 * @dataProvider hostedPaymentStatusRejectedErrors
	 */
	public function testGetLatestPaymentStatusFailuresReturnErrors( $errorCode, $errorDescription ) {
		$hostedPaymentId = 'DUMMY-ID-8915-28e5b79c889641c8ba770f1ba576c1fe';
		$this->setUpResponse( __DIR__ . "/../Data/hostedPaymentStatusRejected$errorCode.response", 200 );
		$this->curlWrapper->expects( $this->once() )
			->method( 'execute' )->with(
				"https://eu.sandbox.api-ingenico.com/v1/1234/hostedcheckouts/$hostedPaymentId",
				'GET'
			);

		$response = $this->provider->getLatestPaymentStatus( [ 'gateway_session_id' => $hostedPaymentId ] );
		$rawResponse = $response->getRawResponse();
		$this->assertNotEmpty( $rawResponse['errors'] );
		$this->assertEquals( $errorCode, $rawResponse['errors'][0]['code'] );
		$this->assertEquals( $errorDescription, $rawResponse['errors'][0]['message'] );
	}

	/**
	 * We don't have an exhaustive list here; the codes below are the failure event
	 * codes that we've been able to evoke so far using the Ingenico test card details
	 */
	public function hostedPaymentStatusRejectedErrors() {
		return [
			[ '430424', 'Unable to authorise' ],
			[ '430475', 'Not authorised' ],
			[ '430327', 'Unable to authorise' ],
			[ '430409', 'Referred' ],
			[ '430330', 'Not authorised' ],
			[ '430306', 'Card expired' ],
			[ '430260', 'Not authorised' ],
		];
	}

	public function testGetLatestPaymentStatusInProgress() {
		$hostedPaymentId = '8915-28e5b79c889641c8ba770f1ba576c1fe';
		$this->setUpResponse( __DIR__ . "/../Data/hostedPaymentStatusIN_PROGRESS.response", 200 );
		$this->curlWrapper->expects( $this->once() )
			->method( 'execute' )->with(
				"https://eu.sandbox.api-ingenico.com/v1/1234/hostedcheckouts/$hostedPaymentId",
				'GET'
			);
		$response = $this->provider->getLatestPaymentStatus( [ 'gateway_session_id' => $hostedPaymentId ] );
		$rawResponse = $response->getRawResponse();
		$this->assertEquals( 'IN_PROGRESS', $rawResponse['status'] );
		$this->assertEquals( 'IN_PROGRESS', $response->getRawStatus() );
		$this->assertEquals( FinalStatus::PENDING, $response->getStatus() );
		$this->assertFalse( $response->isSuccessful() );
	}

	public function testCreatePaymentSession() {
		$params = [
			'use_3d_secure' => false,
			'amount' => 10,
			'currency' => 'USD',
			'recurring' => 0,
			'return_url' => 'https://example.com',
			'processor_form' => 'blah',
			'city' => 'Twin Peaks',
			'street_address' => '708 Northwestern Street',
			'state_province' => 'WA',
			'postal_code' => '98045',
			'email' => 'lpalmer@example.com',
			'order_id' => '19900408',
			'description' => 'Donation to Stop Ghostwood campaign',
			'user_ip' => '127.0.0.1',
			'country' => 'US',
			'language' => 'en_US',
		];
		$this->setUpResponse( __DIR__ . '/../Data/newHostedCheckout.response', 200 );
		$this->curlWrapper->expects( $this->once() )
			->method( 'execute' )->with(
				'https://eu.sandbox.api-ingenico.com/v1/1234/hostedcheckouts',
				'POST',
				$this->anything(),
				$this->callback( function ( $curlData ) {
					$decoded = json_decode( $curlData, true );
					$this->assertSame( [
							'cardPaymentMethodSpecificInput' => [
								'threeDSecure' => [
									'skipAuthentication' => 'true',
								],
							],
							'hostedCheckoutSpecificInput' => [
								'locale' => 'en_US',
								'returnCancelState' => true,
								'paymentProductFilters' => [
									'restrictTo' => [
										'groups' => [ 'cards' ],
									]
								],
								'returnUrl' => 'https://example.com',
								'showResultPage' => false,
								'variant' => 'blah',
							],
							'fraudFields' => [
								'customerIpAddress' => '127.0.0.1',
							],
							'order' => [
								'amountOfMoney' => [
									'amount' => '1000',
									'currencyCode' => 'USD',
								],
								'customer' => [
									'billingAddress' => [
										'city' => 'Twin Peaks',
										'countryCode' => 'US',
										'state' => 'WA',
										'street' => '708 Northwestern Street',
										'zip' => '98045',
									],
									'contactDetails' => [
										'emailAddress' => 'lpalmer@example.com'
									],
									'locale' => 'en_US',
								],
								'references' => [
									'descriptor' => 'Donation to Stop Ghostwood campaign',
									'merchantReference' => '19900408',
								]
							]
						],
						$decoded
					);
					return true;
				} )
			);
		$response = $this->provider->createPaymentSession( $params );
		$this->assertEquals( '8915-28e5b79c889641c8ba770f1ba576c1fe', $response->getPaymentSession() );
		$this->assertEquals(
			'https://payments.test.pay1.secured-by-ingenico.com/pay8915-53ebca407e6b4a1dbd086aad4f10354d:8915-28e5b79c889641c8ba770f1ba576c1fe:9798f4c44ac6406e8288494332d1daa0',
			$response->getRedirectUrl()
		);
	}

	public function testCreatePaymentSessionRecurring() {
		$params = [
			'use_3d_secure' => true,
			'amount' => 10,
			'currency' => 'USD',
			'recurring' => 1,
			'return_url' => 'https://example.com',
			'processor_form' => 'blah',
			'city' => 'Twin Peaks',
			'street_address' => '708 Northwestern Street',
			'state_province' => 'WA',
			'postal_code' => '98045',
			'email' => 'lpalmer@example.com',
			'order_id' => '19900408',
			'description' => 'Monthly donation to Stop Ghostwood campaign',
			'user_ip' => '127.0.0.1',
			'country' => 'US',
			'language' => 'en_US',
		];
		$curlResponse = $this->getParsedCurlWrapperResponse( __DIR__ . '/../Data/newHostedCheckout.response', 200 );
		$this->curlWrapper->expects( $this->once() )
			->method( 'execute' )->with(
				'https://eu.sandbox.api-ingenico.com/v1/1234/hostedcheckouts',
				'POST',
				$this->anything(),
				$this->callback( function ( $curlData ) {
					$decoded = json_decode( $curlData, true );
					$this->assertSame( [
							'cardPaymentMethodSpecificInput' => [
								'tokenize' => 'true',
								'recurring' => [
									'recurringPaymentSequenceIndicator' => 'first',
								]
							],
							'hostedCheckoutSpecificInput' => [
								'isRecurring' => 'true',
								'locale' => 'en_US',
								'returnCancelState' => true,
								'paymentProductFilters' => [
									'restrictTo' => [
										'groups' => [ 'cards' ],
									]
								],
								'returnUrl' => 'https://example.com',
								'showResultPage' => false,
								'variant' => 'blah',
							],
							'fraudFields' => [
								'customerIpAddress' => '127.0.0.1',
							],
							'order' => [
								'amountOfMoney' => [
									'amount' => '1000',
									'currencyCode' => 'USD',
								],
								'customer' => [
									'billingAddress' => [
										'city' => 'Twin Peaks',
										'countryCode' => 'US',
										'state' => 'WA',
										'street' => '708 Northwestern Street',
										'zip' => '98045',
									],
									'contactDetails' => [
										'emailAddress' => 'lpalmer@example.com'
									],
									'locale' => 'en_US',
								],
								'references' => [
									'descriptor' => 'Monthly donation to Stop Ghostwood campaign',
									'merchantReference' => '19900408',
								]
							]
						],
						$decoded
					);
					return true;
				} )
			)->willReturn( $curlResponse );
		$response = $this->provider->createPaymentSession( $params );
		$this->assertEquals( '8915-28e5b79c889641c8ba770f1ba576c1fe', $response->getPaymentSession() );
		$this->assertEquals(
			'https://payments.test.pay1.secured-by-ingenico.com/pay8915-53ebca407e6b4a1dbd086aad4f10354d:8915-28e5b79c889641c8ba770f1ba576c1fe:9798f4c44ac6406e8288494332d1daa0',
			$response->getRedirectUrl()
		);
	}

	/**
	 * Ensure we set the correct parameters for a speculative
	 * tokenization (i.e. Card On File rather than Subscription)
	 */
	public function testCreatePaymentSessionMonthlyConvert() {
		$params = [
			'use_3d_secure' => true,
			'amount' => 10,
			'currency' => 'USD',
			'recurring' => 1,
			'recurring_model' => RecurringModel::CARD_ON_FILE,
			'return_url' => 'https://example.com',
			'processor_form' => 'blah',
			'city' => 'Twin Peaks',
			'street_address' => '708 Northwestern Street',
			'state_province' => 'WA',
			'postal_code' => '98045',
			'email' => 'lpalmer@example.com',
			'order_id' => '19900408',
			'description' => 'Monthly donation to Stop Ghostwood campaign',
			'user_ip' => '127.0.0.1',
			'country' => 'US',
			'language' => 'en_US',
		];
		$curlResponse = $this->getParsedCurlWrapperResponse( __DIR__ . '/../Data/newHostedCheckout.response', 200 );
		$this->curlWrapper->expects( $this->once() )
			->method( 'execute' )->with(
				'https://eu.sandbox.api-ingenico.com/v1/1234/hostedcheckouts',
				'POST',
				$this->anything(),
				$this->callback( function ( $curlData ) {
					$decoded = json_decode( $curlData, true );
					$this->assertSame(
						[
							'cardPaymentMethodSpecificInput' => [
								'tokenize' => 'true',
							],
							'hostedCheckoutSpecificInput' => [
								'locale' => 'en_US',
								'returnCancelState' => true,
								'paymentProductFilters' => [
									'restrictTo' => [
										'groups' => [ 'cards' ],
									]
								],
								'returnUrl' => 'https://example.com',
								'showResultPage' => false,
								'variant' => 'blah',
							],
							'fraudFields' => [
								'customerIpAddress' => '127.0.0.1',
							],
							'order' => [
								'amountOfMoney' => [
									'amount' => '1000',
									'currencyCode' => 'USD',
								],
								'customer' => [
									'billingAddress' => [
										'city' => 'Twin Peaks',
										'countryCode' => 'US',
										'state' => 'WA',
										'street' => '708 Northwestern Street',
										'zip' => '98045',
									],
									'contactDetails' => [
										'emailAddress' => 'lpalmer@example.com'
									],
									'locale' => 'en_US',
								],
								'references' => [
									'descriptor' => 'Monthly donation to Stop Ghostwood campaign',
									'merchantReference' => '19900408',
								]
							]
						],
						$decoded
					);
					return true;
				} )
			)->willReturn( $curlResponse );
		$response = $this->provider->createPaymentSession( $params );
		$this->assertEquals( '8915-28e5b79c889641c8ba770f1ba576c1fe', $response->getPaymentSession() );
		$this->assertEquals(
			'https://payments.test.pay1.secured-by-ingenico.com/pay8915-53ebca407e6b4a1dbd086aad4f10354d:8915-28e5b79c889641c8ba770f1ba576c1fe:9798f4c44ac6406e8288494332d1daa0',
			$response->getRedirectUrl()
		);
	}
}
