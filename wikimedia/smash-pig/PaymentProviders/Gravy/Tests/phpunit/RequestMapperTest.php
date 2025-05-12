<?php

use PHPUnit\Framework\TestCase;
use SmashPig\PaymentProviders\Gravy\Mapper\RequestMapper;

class RequestMapperTest extends TestCase {

	public function testMapToCreatePaymentRequest() {
		$mapper = new RequestMapper();

		$params = [
			'amount' => '100.50',
			'currency' => 'USD',
			'country' => 'US',
			'method' => 'credit_card',
			'order_id' => '12345',
			'email' => 'test@example.com',
			'first_name' => 'John',
			'last_name' => 'Doe',
			'phone' => '1234567890',
			'city' => 'New York',
			'postal_code' => '10001',
			'state_province' => 'NY',
			'street_address' => '123 Main St',
			'employer' => 'Company Inc.',
		];

		// Mock the CurrencyRoundingHelper to return a specific value
		$mockedAmount = 10050; // Amount in minor units

		$expectedRequest = [
			'amount' => $mockedAmount,
			'currency' => 'USD',
			'country' => 'US',
			'payment_method' => [
				'method' => 'credit_card',
			],
			'external_identifier' => '12345',
			'buyer' => [
				'external_identifier' => 'test@example.com',
				'billing_details' => [
					'first_name' => 'John',
					'last_name' => 'Doe',
					'email_address' => 'test@example.com',
					'phone_number' => '1234567890',
					'address' => [
						'city' => 'New York',
						'country' => 'US',
						'postal_code' => '10001',
						'state' => 'NY',
						'line1' => '123 Main St',
						'line2' => null,
						'organization' => 'Company Inc.',
					],
				],
			],
		];

		$result = $mapper->mapToCreatePaymentRequest( $params );

		$this->assertEquals( $expectedRequest, $result );
	}
}
