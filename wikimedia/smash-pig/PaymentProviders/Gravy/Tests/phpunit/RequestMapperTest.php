<?php

use PHPUnit\Framework\TestCase;
use SmashPig\PaymentProviders\Gravy\Mapper\BankPaymentProviderRequestMapper;
use SmashPig\PaymentProviders\Gravy\Mapper\RequestMapper;

/**
 * @group Gravy
 */
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
			'description' => 'Wikimedia Foundation'
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
			"statement_descriptor" => [
				"description" => "Wikimedia Foundation"
			],
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

	public function testMapToSepaRecurringCreatePaymentRequest() {
		$params = [
			'recurring_payment_token' => '0c53bb01-a00b-4627-8c5a-64d692a43291',
			'amount' => 3,
			'currency' => 'EUR',
			'first_name' => 'Testy',
			'last_name' => 'Testeroni',
			'email' => 'ttesteroni@example.com',
			'country' => 'NL',
			'order_id' => '239381286.3',
			'installment' => 'recurring',
			'description' => 'Wikimedia Foundation',
			'recurring' => 1,
			'user_ip' => '11.22.33.44',
			'processor_contact_id' => null,
			'fiscal_number' => null,
			'payment_submethod' => 'sepadirectdebit',
		];
		$mapper = new BankPaymentProviderRequestMapper();
		$request = $mapper->mapToCreatePaymentRequest( $params );
		$this->assertEquals( [
			'amount' => 300,
			'currency' => 'EUR',
			'country' => 'NL',
			'payment_method' => [
				'method' => 'id',
				'id' => '0c53bb01-a00b-4627-8c5a-64d692a43291'
			],
			'external_identifier' => '239381286.3',
			'statement_descriptor' => [
				'description' => 'Wikimedia Foundation'
			],
			'buyer' => [
				'external_identifier' => 'ttesteroni@example.com',
				'billing_details' => [
					'first_name' => 'Testy',
					'last_name' => 'Testeroni',
					'email_address' => 'ttesteroni@example.com',
					'phone_number' => null,
					'address' => [
						'city' => null,
						'country' => 'NL',
						'postal_code' => null,
						'state' => null,
						'line1' => null,
						'line2' => null,
						'organization' => null,
					],
				],
			],
			'intent' => 'capture',
			'merchant_initiated' => true,
			'is_subsequent_payment' => true,
			'payment_source' => 'recurring',
			'user_ip' => '11.22.33.44',
		], $request );
	}
}
