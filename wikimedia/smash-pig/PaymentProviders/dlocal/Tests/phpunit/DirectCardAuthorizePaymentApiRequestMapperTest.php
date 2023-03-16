<?php

namespace SmashPig\PaymentProviders\dlocal\Tests\phpunit;

use PHPUnit\Framework\TestCase;
use SmashPig\PaymentProviders\dlocal\ApiMappers\DirectCardAuthorizePaymentApiRequestMapper;

/**
 * @group Dlocal
 * @group DlocalMapperTest
 */
class DirectCardAuthorizePaymentApiRequestMapperTest extends TestCase {

	public function testInitializeCardPaymentApiRequestMapper(): void {
		$class = new DirectCardAuthorizePaymentApiRequestMapper();
		$this->assertInstanceOf( DirectCardAuthorizePaymentApiRequestMapper::class, $class );
	}

	public function testCardPaymentApiRequestMapperTransformInputToExpectedOutput(): void {
		$params = $this->getBaseParams();
		$apiParams = $params['params'];
		$apiParams['payment_token'] = 'fake-token';
		$baseParams = $params['transformedParams'];
		$apiRequestMapper = new DirectCardAuthorizePaymentApiRequestMapper();
		$apiRequestMapper->setInputParams( $apiParams );

		$expectedOutput = array_merge(
			$baseParams,
			[
				'card' => [
					'token' => $apiParams['payment_token'],
					'capture' => false
				],
			]
		);

		$this->assertEquals( $expectedOutput, $apiRequestMapper->getAll() );
	}

	public function testCardPaymentWithRecurringApiRequestMapperTransformInputToExpectedOutput(): void {
		$params = $this->getBaseParams();
		$apiParams = $params['params'];
		$apiParams['payment_token'] = 'fake-token';
		$apiParams['recurring'] = '1';
		$baseParams = $params['transformedParams'];

		$apiRequestMapper = new DirectCardAuthorizePaymentApiRequestMapper();
		$apiRequestMapper->setInputParams( $apiParams );

		$expectedOutput = array_merge(
			$baseParams,
			[
				'card' => [
					'token' => $apiParams['payment_token'],
					'save' => true,
					'capture' => false
				],
			]
		);

		$this->assertEquals( $expectedOutput, $apiRequestMapper->getAll() );
	}

	public function testCardPayment3DSecureApiRequestMapperTransformInputToExpectedOutput(): void {
		$params = $this->getBaseParams();
		$apiParams = $params['params'];
		$apiParams['payment_token'] = 'fake-token';
		$apiParams['use_3d_secure'] = true;
		$baseParams = $params['transformedParams'];

		$apiRequestMapper = new DirectCardAuthorizePaymentApiRequestMapper();
		$apiRequestMapper->setInputParams( $apiParams );

		$expectedOutput = array_merge(
			$baseParams,
			[
				'card' => [
					'token' => $apiParams['payment_token'],
					'capture' => false
				],
				'three_dsecure' => [
					'force' => true
				]
			]
		);
		$this->assertEquals( $expectedOutput, $apiRequestMapper->getAll() );
	}

	private function getBaseParams(): array {
		$input = [
			'order_id' => '123.3',
			'amount' => '100',
			'currency' => 'MXN',
			'country' => 'MX',
			'first_name' => 'Lorem',
			'last_name' => 'Ipsum',
			'email' => 'li@mail.com',
			'fiscal_number' => '12345',
			'contact_id' => '12345',
			'state_province' => 'lore',
			'city' => 'lore',
			'postal_code' => 'lore',
			'street_address' => 'lore',
			'street_number' => 2,
			'user_ip' => '127.0.0.1'
		];
		$transformedParams = [
			'amount' => $input['amount'],
			'currency' => $input['currency'],
			'country' => $input['country'],
			'order_id' => $input['order_id'],
			'payment_method_id' => 'CARD',
			'payment_method_flow' => 'DIRECT',
			'payer' => [
				'name' => $input['first_name'] . ' ' . $input['last_name'],
				'email' => $input['email'],
				'document' => $input['fiscal_number'],
				'user_reference' => $input['contact_id'],
				'ip' => $input['user_ip'],
				'address' => [
					'state' => $input['state_province'],
					'city' => $input['city'],
					'zip_code' => $input['postal_code'],
					'street' => $input['street_address'],
					'number' => $input['street_number'],
				],
			]
		];

		return [
			'params' => $input,
			'transformedParams' => $transformedParams
		];
	}

}
