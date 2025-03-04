<?php

namespace SmashPig\PaymentProviders\dlocal\Tests\phpunit;

use PHPUnit\Framework\TestCase;
use SmashPig\PaymentProviders\dlocal\Api;
use SmashPig\PaymentProviders\dlocal\ApiMappers\RecurringChargeHostedPaymentApiRequestMapper;

/**
 * @group Dlocal
 * @group DlocalMapperTest
 */
class RecurringChargeHostedPaymentApiRequestMapperTest extends TestCase {

	public function testRecurringChargeInitializePaymentApiRequestMapper(): void {
		$class = new RecurringChargeHostedPaymentApiRequestMapper();
		$this->assertInstanceOf( RecurringChargeHostedPaymentApiRequestMapper::class, $class );
	}

	public function testRecurringChargeHostedPaymentApiRequestMapperTransformInputToExpectedOutput(): void {
		$params = $this->getBaseParams();
		$apiParams = $params['params'];
		$expectedOutput = $params['transformedParams'];
		$apiRequestMapper = new RecurringChargeHostedPaymentApiRequestMapper();
		$apiRequestMapper->setInputParams( $apiParams );
		$this->assertEquals( $expectedOutput, $apiRequestMapper->getAll() );
	}

	public function testRecurringChargeHostedPaymentApiParamsWithMismatchCountryCurrency() {
		$params = $this->getBaseParams();
		$apiParams = $params['params'];
		// Change country to something different from the set currency
		$apiParams['country'] = 'IN';
		$expectedOutput = $params['transformedParams'];
		$apiRequestMapper = new RecurringChargeHostedPaymentApiRequestMapper();
		$apiRequestMapper->setInputParams( $apiParams );
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
			'user_ip' => '127.0.0.1',
			'recurring_payment_token' => 'fake-token'
		];
		$transformedParams = [
			'amount' => $input['amount'],
			'currency' => $input['currency'],
			'country' => $input['country'],
			'order_id' => $input['order_id'],
			'payment_method_flow' => Api::PAYMENT_METHOD_FLOW_DIRECT,
			'description' => 'recurring charge',
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
			],
			'wallet' => [
				'token' => $input['recurring_payment_token'],
				'recurring_info' => [
					'prenotify' => true,
				],
			],
		];

		return [
			'params' => $input,
			'transformedParams' => $transformedParams
		];
	}
}
