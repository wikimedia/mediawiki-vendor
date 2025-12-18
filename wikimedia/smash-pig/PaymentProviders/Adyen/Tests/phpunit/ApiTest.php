<?php
namespace SmashPig\PaymentProviders\Adyen\Test;

use SmashPig\PaymentData\FinalStatus;
use SmashPig\PaymentProviders\Adyen\Api;
use SmashPig\Tests\BaseSmashPigUnitTestCase;

/**
 * @group Adyen
 */
class ApiTest extends BaseSmashPigUnitTestCase {

	/**
	 * @var Api
	 */
	protected $api;

	public function setUp(): void {
		parent::setUp();
		$providerConfiguration = $this->setProviderConfiguration( 'adyen' );
		$providerConfiguration->overrideObjectInstance( 'curl/wrapper', $this->curlWrapper );
		// FIXME: we should probably give all these params as constructor-parameters
		// to the Api object as we do in Ingenico
		$providerConfiguration->override( [
			'accounts' => [
				'test' => [
					'ws-api-key' => 'K1ck0utTh3J4ms',
				]
			]
		] );
		$this->api = new Api();
	}

	public function testGetAmountFormatsNonFractionalCurrency() {
		// open up access to the private getAmount method
		$reflectionClass = new \ReflectionClass( Api::class );
		$reflectionMethod = $reflectionClass->getMethod( 'getArrayAmount' );
		$reflectionMethod->setAccessible( true );

		// mock the Api class to skip constructor call
		$apiMock = $this->getMockBuilder( Api::class )
			->disableOriginalConstructor()
			->getMock();

		// getAmount params
		$params = [
			'currency' => 'JPY',
			'amount' => '150'
		];

		$expected = [
			'currency' => 'JPY',
			'value' => 150
		];

		// call getAmount via reflection
		$result = $reflectionMethod->invoke( $apiMock, $params );
		$this->assertEquals( $expected, $result );
	}

	public function testGetAmountFormatsFractionalCurrency() {
		// open up access to the private getAmount method
		$reflectionClass = new \ReflectionClass( Api::class );
		$reflectionMethod = $reflectionClass->getMethod( 'getArrayAmount' );
		$reflectionMethod->setAccessible( true );

		// mock the Api class to skip constructor call
		$apiMock = $this->getMockBuilder( Api::class )
			->disableOriginalConstructor()
			->getMock();

		// getAmount params
		$params = [
			'currency' => 'USD',
			'amount' => '9.99'
		];

		$expected = [
			'currency' => 'USD',
			'value' => 999
		];

		// call getAmount via reflection
		$result = $reflectionMethod->invoke( $apiMock, $params );
		$this->assertEquals( $expected, $result );
	}

	public function testGetAmountFormatsExponent3Currency() {
		// open up access to the private getAmount method
		$reflectionClass = new \ReflectionClass( Api::class );
		$reflectionMethod = $reflectionClass->getMethod( 'getArrayAmount' );
		$reflectionMethod->setAccessible( true );

		// mock the Api class to skip constructor call
		$apiMock = $this->getMockBuilder( Api::class )
			->disableOriginalConstructor()
			->getMock();

		// getAmount params
		$params = [
			'currency' => 'IQD',
			'amount' => '74.698'
		];

		$expected = [
			'currency' => 'IQD',
			'value' => 74698
		];

		// call getAmount via reflection
		$result = $reflectionMethod->invoke( $apiMock, $params );
		$this->assertEquals( $expected, $result );
	}

	public function testCreatePaymentFromEncryptedDetailsSuccess() {
		$params = $this->getCreatePaymentTestParams();
		$responseBody = file_get_contents( __DIR__ . '/../Data/createdEncryptedPayment.json' );
		$this->curlWrapper->expects( $this->once() )
			->method( 'execute' )
			->with(
				$this->callback( function ( $url ) {
					$this->assertStringEndsWith( '/payments', $url );
					return true;
				} ),
				'POST',
				$this->callback( function ( $actualHeaders ) {
					$this->assertArrayHasKey( 'Idempotency-Key', $actualHeaders );
					unset( $actualHeaders['Idempotency-Key'] );
					$this->assertEquals( [
						'Content-Length' => '4700',
						'x-API-key' => 'K1ck0utTh3J4ms',
						'content-type' => 'application/json'
					], $actualHeaders );
					return true;
				} ), json_encode( [
				'amount' => [
					'currency' => 'EUR',
					'value' => 2325
				],
				'reference' => $params['order_id'],
				'paymentMethod' => $params['encrypted_payment_data'] + [
					'type' => 'scheme',
					'holderName' => 'Wayne Kramer'
				],
				'merchantAccount' => 'test',
				'additionalData' => [
					'manualCapture' => true,
				],
				'returnUrl' => $params['return_url'],
				'origin' => 'https://paymentstest2.wmcloud.org',
				'channel' => 'Web',
				'shopperEmail' => 'wkramer@mc5.net',
				'shopperIP' => '127.0.0.1',
				'shopperName' => [
					'firstName' => 'Wayne',
					'lastName' => 'Kramer'
				],
				'billingAddress' => [
					'city' => 'Detroit',
					'country' => 'US',
					'houseNumberOrName' => '',
					'postalCode' => '48204',
					'stateOrProvince' => 'MI',
					'street' => '8952 Grand River Avenue',
				],
				'shopperStatement' => 'Wikimedia Foundation',
			] ) )
			->willReturn( [
				'body' => $responseBody,
				'headers' => [], // This API doesn't care
				'status' => 200,
				'elapsed' => 2,
			] );
		$response = $this->api->createPaymentFromEncryptedDetails( $params );
		$this->assertEquals( json_decode( $responseBody, true ), $response );
	}

	public function testCreatePaymentUnsupportedCard() {
		$responseBody = file_get_contents( __DIR__ . '/../Data/auth-unsupported.json' );
		$this->curlWrapper->expects( $this->once() )
			->method( 'execute' )
			->willReturn( [
				'body' => $responseBody,
				'headers' => [], // This API doesn't care
				'status' => 500,
				'elapsed' => 2,
			] );
		$response = $this->api->createPaymentFromEncryptedDetails( $this->getCreatePaymentTestParams() );
		$this->assertEquals( '905_1', $response['errorCode'] );
	}

	/**
	 * Make sure we send the right params for a CZ online banking auth
	 * @return void
	 * @throws \SmashPig\Core\ApiException
	 */
	public function testCzechOnlineBankingCreatePayment() {
		$params = [
			'amount' => '100.00',
			'country' => 'CZ',
			'currency' => 'CZK',
			'description' => 'Wikimedia Foundation',
			'email' => 'testytest@example.com',
			'first_name' => 'Testy',
			'last_name' => 'Test',
			'order_id' => '48991764.1',
			'postal_code' => '0',
			'return_url' => 'https://localhost:9001/index.php?title=Special:AdyenCheckoutGatewayResult&order_id=48991764.1&wmf_token=7b760d3a08327538d824c00a1c042a29%2B%5C&amount=100.00&currency=CZK&payment_method=bt&payment_submethod=&utm_source=..bt',
			'street_address' => 'N0NE PROVIDED',
			'user_ip' => '172.20.0.1',
			'issuer_id' => 'cs',
		];
		$expectedRestParams = [
			'amount' => [
				'currency' => 'CZK',
				'value' => 10000,
			],
			'reference' => '48991764.1',
			'merchantAccount' => 'test',
			'paymentMethod' => [
				'type' => 'onlineBanking_CZ',
				'issuer' => 'cs',
			],
			'returnUrl' => 'https://localhost:9001/index.php?title=Special:AdyenCheckoutGatewayResult&order_id=48991764.1&wmf_token=7b760d3a08327538d824c00a1c042a29%2B%5C&amount=100.00&currency=CZK&payment_method=bt&payment_submethod=&utm_source=..bt',
			'additionalData' => [
				'manualCapture' => false,
			],
			'shopperEmail' => 'testytest@example.com',
			'shopperIP' => '172.20.0.1',
			'shopperName' => [
				'firstName' => 'Testy',
				'lastName' => 'Test',
			],
			'billingAddress' => [
				'city' => 'NA',
				'country' => 'CZ',
				'houseNumberOrName' => '',
				'postalCode' => '0',
				'stateOrProvince' => 'NA',
				'street' => 'N0NE PROVIDED',
			],
		];
		$this->curlWrapper->expects( $this->once() )
			->method( 'execute' )
			->with(
				$this->callback( function ( $url ) {
					$this->assertStringEndsWith( '/payments', $url );
					return true;
				} ),
				'POST',
				$this->anything(), // ignore headers for this test
				json_encode( $expectedRestParams )
			)->willReturn( [
				'body' => '{}', // ignoring returned content for this test
				'headers' => [], // This API doesn't care
				'status' => 200,
				'elapsed' => 2,
			] );
		$this->api->createBankTransferPaymentFromCheckout( $params );
	}

	protected function getCreatePaymentTestParams() {
		return [
			'currency' => 'EUR',
			'amount' => '23.25',
			'order_id' => '1234.1',
			'encrypted_payment_data' => [
				'encryptedCardNumber' => 'adyenjs_0_1_25$Wzozxz+Xa60jIs/aAKpJNP473zHTNt1UNqW1PoS6C6mJBkxPZosFDYofJDJqlBIhOUwnPVsRYYiIaqPreA70LDlhL+bWG4R8cRzkTl3dblCb1I/KL4cmfVUfLNnJ0zZ1VTBbi2qry1FR22/F/DsM0BAJH/CuPKOO8+Oi5XLrEfURcGSPHppicKqNYwAoTElm3O5z7n0aNuGAs9L7DQszmUoh+rIqy0Nk0lrXZUR9Ou1C+uMM0IseQN3qo4uMj4ufAFiRy2J1QUXC92ZYCkLRfD/Rt4yBXvs/g0GgEHcRHKCXoXyroNPYf0FVUxKkSr0DAtyzA0g3RNbEu9EoEykk3g==$t71W1Gi6McNbs3M3WyXsw8LtzX+WcrXklH/+HIlNe9Hr/6FzvjVpkdrkgbSxvAbyxs1OVdTfu1HZyMrL7VS5V1nHhJvEWseqZZBySF6YIRYl0QlZWSH52kFQg5ZKygPart+Z4/oqSObMPIvcydTwC37i37b65wDi5PdtxmLFpls+oBGV2Ovij2ub0wPsH7+u8tmhTrgYQTmmWYILoohuODx1zD78/uh9Q5yc3dTwj5yxfChJsHkfGOR85UihATYUXjrvBxHAF6xHIa1GjZ8ZP+ARL6FkBiUAKoEzju+T1Jjo6PPHo+E59G4B39KTY514LNPYVTptEzpA5WtP5SqAjZrL80XSWnSaKEbptMecO/+Wzr39DZXdYTOErwOTJm3pGGd79H+GSvnyeZa51P2WV9WsKRMZ7w2DLfOPYKJL+dyuhIt2zzAXbIO3l8rOwl+UEEfxOcb8e9B5+FbDdceZqpiTaE7+pdvyf/gh/+MbxUKKAAtF/AKL264hGLheyJK8mhGvGOHIGllPPfc3MKewdmCcb9HKXM4+R54yYESHZGYu2NYTCSCHb1MXMJ8LWWxcqJPd52yZbTtWqBPOXyn0DSS3/FXVKoozDNLv6ctk5pSYYCwa5cyxiBQ1jNggTexBmBlOe4/2uFcrQXrlfOeatGjnoJRwjufNgfCdNge5BhY1qE7WbBBfOBORuGYBNGVd3nDYe3cN+BreWR6S4+JPmrFq6s2iaNASr3AGUAHOtfNRzcsYV7yYav0aPTLYHRoc31fCIA==',
				'encryptedExpiryMonth' => 'adyenjs_0_1_25$W+Jspf1bZ2AGu6lSb8riFf3RWS6QGpeh5nyEqBI9QkQKEAVn3v78a5yDEivaVHJqemoSBO8bHIq5VAkkzMmu0pd05u+CWVmemxANXTtFnEhf2CCFxwjYJgf49nNH1g8Hy2DnAX8PPNYI2sbFRKuTezhrIQbzqGUZDCkmrGGvKMTFLMUb2w48Pmyr/hQQ0lfZ8hI38sLflwQom2HQxmzoRc0NazIz9OCyltCTPMHEnLGBSAwho63YkcZPfAGBr07UeXP7awO8eX6nat6Th5HyEOochZNz3sIG9WEnLRL9SvIc/cyvFkjQPErH/+CPhpOq1kddOyJdvM1v5JsiNWunOg==$XRLzb9D6SNhKk0ZFxDqLPz1tva4ZSRwNs4AGRqJ1gPz/SAA2zojKE0V+6pAXL6vf9ZtOdlk5gEDN0UjaNOOzMFP9I+LjXh2bl7NikoBhQp0TjXJDWqTWbMjcCcBERu+K1xjbg+nBcHSPdkDXWoRdJC+p9OMOSuapSrmhLMrTJDEYpov7aPJ7VRBOIvys6929AVS+pno19M6BQ/YQ34UntGQRsE/a2hPCeRiG3qIaur3Y4Y2ABih2CBdBxrTQlZLymhbwuoZKqhnTXX8KxHkIQb2Q+CmbKyKf5GIMaMGx5JIZVkkeHVf4S0NEsu3av1eJPPy6nonViWQ1odwV9Cg7O0tW0cUG4AwEeK0r8O9/stf6p0FTHFuisyMxXBDJTVd+HIis2daJVTx/YrqV9XDAhW/a9Eh05PVEZWaczPqR0Vi0TRM=',
				'encryptedExpiryYear' => 'adyenjs_0_1_25$XoUIwK1nyHSn1HpicUTcAmCORBa2s7/6gb1as/nJq72q3wdYzkNas/HKD0cizpVmnv2MoH1tMMS7xY3XOu2VbgVmkyRNO3R5W0ku4OIlUroIvDRhemHWuNmgSi38wXoM6efAbQ/S4VyuUx/W2jAOv8C/zLZ9xO3DQDUj5v0u7fjJfmYd8qIPrOKHdfhsuFrvoW162xEMv72cez7Ce3nCbPrECTXbpSy2PSGP6BWy1bkMexBehjMrnmOzCgSyoTVH3/KRWkOsIUwf5craxH7PEeqJk1GVbvIXTYHLpKauAZL6l1zSLfMbVgLhGrVpWJ4rmUh3ygHfl+XYmrIVkw7GTg==$p678q/qgksVc5qGyDQTlc5og+0Z7Dmhk2APYq7UP7LVvRYNjdbIqpfEv+QdNPX80VzZAXJH3uoQlQw2ptt/3ZE67zD3ueSf0vUqngypGhC7qv/997NyNPSuBMZDs78i4L0UGFivuK3NnV4ZEjnTsoakX0Le8cur3YBN+jM5DAlSS/7YxjBsUb9Pl5Sh73qBCVL5iGVVoohik25NEh3kRx8C5HPM+G2Quxs0zDkyhz0ndMuiHuKC2uQGQwaO9NmwXAXbC0GbLe8QbEO9oSndi+LG2m/DFj3T3kEfcst7uOAfclpaYkZrbkhGwzxZITPPh9+jDCP9w0bbBtg6/A3GmA0eird5l9fV3ziXhXRZabVUsijuExqD5aaV6M/uWqazPAW5nYLjGM9buhCtxO0LcKs7kSs+aEdHEWfXIeMGyXoPWKijS',
				'encryptedSecurityCode' => 'adyenjs_0_1_25$Sn6D6UB3yLAX+5Syez/rsy8X/xzPJPEwaqzTMhFGGVOmq6lfSf13ZnOyGvYyhiySWPOAAjC1j3GEPedDpQmE4qAX7O71Ve0pvAseNqbwAw2iSLTywSkXAqARn8kzLevDYAeM/s/upeBwpwzeGxxfwCuCstScFyuLjPW2+2zmm0bdntqJs2yvzCPPIGQXz0EbUdQ/0+SIvfCzSfvfUwGR9CcqTDyQD/LuOV+ZJHm1ipMhr+vBO0I4E7onvnYCly5hKiduhmNsVBg0O2IWMB8C2SnkXfvylGDbpOD9CcFnAzmG/1uuX88AKuThPyC6eJt3J7k3WnnK2w18sepSterWew==$22Y40V1hZvajAp/cJp+g25FpZCsnXXjAIPVk1xF80OBYERK3oVDrNNZRA3bgLfCfRPvKhBrQq1H7OmZMCNbYzK4lBn3S6m6aeNNWWDpqFcFnovu5dE1FKaVCOjA47vegOC5bSWBaCUkfJySxIhfuupAygamv4f6OU3dV12voSDvh89AM3RhlCiE/8y/BtX5LDTcKSPRYn4ecg5g4yjOfBNaoj0QecOzTsYNRiGZ3DnIu6t79Kmmp8lxoLw1EcnSM/pDT10S4Og6eaSDve5KJvFlP0gzLTPlcHbsgpKBqNUwQ6cWldapOaUKr+eL58fSi0QR7eMCN80F66SJ3ueFG+NjnBKPLxf8mC3IpDH5DiV07TXt1tuO3B8lLQ0C8y9RBw//Rs/VB7dZtug4UFe/okDchypL1vCy2QRviLJY840o9JQ5PF1rCH8UL3N0eLmfUmnZpO6wF5tetPb8u+vnH4DOpR3Z+FQAnwZ6VyBnjK1/xGzi9V1NCiMKA+ZqnAsnUFxZU8JT0RbsKZCLHfRC41hXqs2hj6kwx5s1lZVINpze0Z8TqOoQLDpokDCERlrGJoO5G5aUiDe5NwRop4i58ClyEt4KJR03BBJrw4t0pMN8qUl32eXjj1Jc0sedsWN75uB5eJ1MBAps6IheDeDDtvXOUjQUjr8dJcgOZEbWkX7yKqcUaSLhUjL3qlAmFwivcKsGUi0i290+Oj1FV/D+ffyEV+QM=',
			],
			'city' => 'Detroit',
			'street_address' => '8952 Grand River Avenue',
			'country' => 'US',
			'description' => 'Wikimedia Foundation',
			'email' => 'wkramer@mc5.net', // Not donor data, just a band reference. Kick out the Jams!
			'first_name' => 'Wayne',
			'last_name' => 'Kramer',
			'postal_code' => '48204',
			'return_url' => 'https://paymentstest2.wmcloud.org/index.php?title=Special:AdyenCheckoutGatewayResult&order_id=1234.1&wmf_token=9b5527285f64111d11fb9dc8579ad147%2B%5C',
			'state_province' => 'MI',
			'user_ip' => '127.0.0.1',
		];
	}

	public function testCreateChileanPesos() {
		$params = $this->getCreatePaymentTestParams();
		$params['amount'] = 10400;
		$params['currency'] = 'CLP';
		$responseBody = file_get_contents( __DIR__ . '/../Data/createdEncryptedPayment.json' );
		$this->curlWrapper->expects( $this->once() )
			->method( 'execute' )
			->with(
				$this->anything(),
				'POST',
				$this->anything(),
				$this->callback( function ( $encodedParams ) {
					$decodedParams = json_decode( $encodedParams, true );
					$this->assertEquals( 1040000, $decodedParams['amount']['value']);
					return TRUE;
				} ) )
			->willReturn( [
				'body' => $responseBody,
				'headers' => [], // This API doesn't care
				'status' => 200,
				'elapsed' => 2,
			] );
		$this->api->createPaymentFromEncryptedDetails( $params );
	}
}
