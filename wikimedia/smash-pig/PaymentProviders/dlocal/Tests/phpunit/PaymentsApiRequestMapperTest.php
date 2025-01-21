<?php

namespace SmashPig\PaymentProviders\dlocal\Tests\phpunit;

use Monolog\Test\TestCase;
use SmashPig\PaymentProviders\dlocal\ApiMappers\PaymentApiRequestMapper;

/**
 * @group Dlocal
 * @group DlocalMapperTest
 */
class PaymentsApiRequestMapperTest extends TestCase {

	public function testInitializePaymentApiRequestMapper(): void {
		$class = new PaymentApiRequestMapper();
		$this->assertInstanceOf( PaymentApiRequestMapper::class, $class );
	}

	public function testPaymentApiRequestMapperTransformInputToExpectedOutput(): void {
		$params = $this->getBaseParams();
		$apiParams = $params['params'];
		$expectedOutput = $params['transformedParams'];
		$apiRequestMapper = new PaymentApiRequestMapper();
		$apiRequestMapper->setInputParams( $apiParams );

		$this->assertEquals( $expectedOutput, $apiRequestMapper->getAll() );
	}

	public function testPaymentApiRequestMapperTransformInputToExpectedOutputWithReturnUrl(): void {
		$returnUrl = 'return-link';
		$params = $this->getBaseParams();
		$apiParams = $params['params'];
		$apiParams['return_url'] = $returnUrl;
		$expectedOutput = $params['transformedParams'];
		$apiRequestMapper = new PaymentApiRequestMapper();
		$apiRequestMapper->setInputParams( $apiParams );

		$expectedOutput = array_merge( $expectedOutput, [
			'callback_url' => $returnUrl
		] );

		$this->assertEquals( $expectedOutput, $apiRequestMapper->getAll() );
	}

	public function testPaymentApiRequestMapperTransformInputToExpectedOutputWithCallbackAndRedirectUrl(): void {
		$returnUrl = 'return-link';

		$params = $this->getBaseParams();
		$apiParams = $params['params'];
		$apiParams['return_url'] = $returnUrl;
		$expectedOutput = $params['transformedParams'];
		$apiRequestMapper = new PaymentApiRequestMapper();
		$apiRequestMapper->setInputParams( $apiParams );

		$expectedOutput = array_merge( $expectedOutput, [
			'callback_url' => $returnUrl
		] );

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
