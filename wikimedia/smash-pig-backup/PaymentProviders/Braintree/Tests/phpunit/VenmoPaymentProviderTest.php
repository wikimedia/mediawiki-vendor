<?php

namespace SmashPig\PaymentProviders\Braintree\Tests;

use SmashPig\PaymentData\FinalStatus;
use SmashPig\PaymentProviders\Braintree\VenmoPaymentProvider;

/**
 * @group Braintree
 */
class VenmoPaymentProviderTest extends BaseBraintreeTest {

	public function setUp(): void {
		parent::setUp();
	}

	public function testVenmoPaymentWithNoDeviceDataError() {
		$request = [
			"payment_token" => "fake-valid-nonce",
			"order_id" => '123.3',
			"amount" => '1.00',
			"email" => 'test@gmail.com',
			"gateway_session_id" => 'xxxx',
			"currency" => "USD"
		];

		$provider = new VenmoPaymentProvider();
		$response = $provider->createPayment( $request );
		$validationError = $response->getValidationErrors();
		$this->assertEquals( $validationError[0]->getField(), 'device_data' );
	}

	public function testVenmoPaymentWithNoGatewaySessionIdAndEmail() {
		$request = [
			"payment_token" => "fake-valid-nonce",
			"order_id" => '123.3',
			"amount" => '1.00',
			"email" => '',
			"gateway_session_id" => '',
			"currency" => "USD",
			"device_data" => '{}'
		];

		$provider = new VenmoPaymentProvider();
		$response = $provider->createPayment( $request );
		$validationError = $response->getValidationErrors();
		$this->assertEquals( $validationError[0]->getField(), 'gateway_session_id' );
	}

	public function testVenmoNotUSDError() {
		$request = [
			"payment_token" => "fake-valid-nonce",
			"order_id" => '123.3',
			"amount" => '1.00',
			"device_data" => '{}',
			"email" => 'test@gmail.com',
			"gateway_session_id" => 'xxxx',
			"currency" => "EUR"
		];

		$provider = new VenmoPaymentProvider();
		$response = $provider->createPayment( $request );
		$validationError = $response->getValidationErrors();
		$this->assertEquals( $validationError[0]->getField(), 'currency' );
	}

	public function testCreatePaymentWithoutEmail() {
		$txn_id = "dHJhbnNhY3Rpb25fYXIxMTNuZzQ";
		$customer = [
			'venmoUserId' => '12345',
			'venmoUserName' => 'venmojoe',
			'firstName' => 'Jimmy',
			'lastName' => 'Wales',
			'phoneNumber' => '131313131',
			'email' => 'mockjwales@wikimedia.org',
		];
		$request = [
			"payment_token" => "fake-valid-nonce",
			"order_id" => '123.3',
			"amount" => '1.00',
			"device_data" => '{}',
			"gateway_session_id" => 'xxxx',
			"currency" => 'USD'
		];
		$this->api->expects( $this->once() )
			->method( 'fetchCustomer' )
			->willReturn( [
				'data' => [ 'node' => [
					'payerInfo' => [
						'email' => $customer['email'],
						'firstName' => $customer['firstName'],
						'lastName' => $customer['lastName'],
						'phoneNumber' => $customer['phoneNumber'],
					]
				] ]
			] );
		$this->api->expects( $this->once() )
			->method( 'authorizePaymentMethod' )
			->willReturn( [
				'data' => [
					'authorizePaymentMethod' => [
						'transaction' => [
							'id' => $txn_id,
							'status' => "AUTHORIZED",
							'customer' => [
								'id' => $customer['venmoUserId'],
								'lastName' => $customer['lastName'],
								'firstName' => $customer['firstName'],
								'email' => $customer['email'],
								'phoneNumber' => $customer['phoneNumber'],
							],
							'paymentMethodSnapshot' => [
								'username' => $customer['venmoUserName'],
								'venmoUserId' => $customer['venmoUserId'],
							]
						]
					] ],
				'extensions' => [
					'requestId' => 'ffea98e0-29d5-49d1-a9fd-ad316192a59c'
				]
			] );
		$provider = new VenmoPaymentProvider();
		$response = $provider->createPayment( $request );
		$donor_details = $response->getDonorDetails();
		$this->assertEquals( FinalStatus::PENDING_POKE, $response->getStatus() );
		$this->assertEquals( $txn_id, $response->getGatewayTxnId() );
		$this->assertEquals( $customer['firstName'], $donor_details->getFirstName() );
		$this->assertEquals( $customer['lastName'], $donor_details->getLastName() );
		$this->assertEquals( $customer['email'], $donor_details->getEmail() );
		$this->assertEquals( $customer['phoneNumber'], $donor_details->getPhone() );
		$this->assertEquals( $customer['venmoUserId'], $donor_details->getCustomerId() );
		$this->assertEquals( $customer['venmoUserName'], $donor_details->getUserName() );
	}

	/**
	 * Test fetch customer info if no customer email passed from client side
	 * @return void
	 */
	public function testFetchCustomer() {
		$payment_context_id = 'cGF5bWVudGNvbnRleHRfbXM5ZnFtdzlneHJtMmJwMyM3YTliNjk1ZC0zZjZjLTQ5NTItOTI4Ny1mZWE4OTI5NTU0YzQ=';
		$expectEmail = 'tt@tt.tt';
		$this->api->expects( $this->once() )
			->method( 'fetchCustomer' )
			->willReturn( [
				'data' => [ 'node' => [
					'payerInfo' => [
						'email' => $expectEmail,
					]
				] ]
			] );

		$provider = new VenmoPaymentProvider();
		$donorDetails = $provider->fetchCustomerData( $payment_context_id );
		$this->assertEquals( $expectEmail, $donorDetails->getEmail() );
	}

	public function testAuthorizePaymentVenmo() {
		$txn_id = "dHJhbnNhY3Rpb25fYXIxMTNuZzQ";

		$customer = [
			'venmoUserId' => '12345',
			'venmoUserName' => 'venmojoe',
			'firstName' => 'Jimmy',
			'lastName' => 'Wales',
			'phoneNumber' => '131313131',
			'email' => 'mockjwales@wikimedia.org',
		];
		$request = [
			"payment_token" => "fake-valid-nonce",
			"order_id" => '123.3',
			"amount" => '1.00',
			"device_data" => '{}',
			'first_name' => $customer['firstName'],
			'last_name' => $customer['lastName'],
			'email' => $customer['email'],
			'phone' => $customer['phoneNumber'],
			"currency" => 'USD'
		];
		$this->api->expects( $this->once() )
			->method( 'authorizePaymentMethod' )
			->willReturn( [
				'data' => [
					'authorizePaymentMethod' => [

						'transaction' => [
							'id' => $txn_id,
							'status' => "AUTHORIZED",
							'customer' => [
								'id' => $customer['venmoUserId'],
								'lastName' => $customer['lastName'],
								'firstName' => $customer['firstName'],
								'email' => $customer['email'],
								'phoneNumber' => $customer['phoneNumber'],
							],
							'paymentMethodSnapshot' => [
								'username' => $customer['venmoUserName'],
								'venmoUserId' => $customer['venmoUserId'],
							]
						]
					] ],
				'extensions' => [
					'requestId' => 'ffea98e0-29d5-49d1-a9fd-ad316192a59c'
				]
			] );

		$provider = new VenmoPaymentProvider();
		$response = $provider->createPayment( $request );
		$donor_details = $response->getDonorDetails();
		$this->assertEquals( FinalStatus::PENDING_POKE, $response->getStatus() );
		$this->assertEquals( $txn_id, $response->getGatewayTxnId() );
		$this->assertEquals( $customer[ 'firstName' ], $donor_details->getFirstName() );
		$this->assertEquals( $customer[ 'lastName' ], $donor_details->getLastName() );
		$this->assertEquals( $customer[ 'email' ], $donor_details->getEmail() );
		$this->assertEquals( $customer[ 'phoneNumber' ], $donor_details->getPhone() );
		$this->assertEquals( $customer['venmoUserId'], $donor_details->getCustomerId() );
		$this->assertEquals( $customer['venmoUserName'], $donor_details->getUserName() );
	}

	public function testAuthorizePaymentVenmoErrordueToInvalidField() {
		$txn_id = "dHJhbnNhY3Rpb25fYXIxMTNuZzQ";

		$customer = [
			'venmoUserId' => '12345',
			'venmoUserName' => 'venmojoe',
			'firstName' => 'Jimmy',
			'lastName' => 'Wales',
			'phoneNumber' => '131313131',
			'email' => 'mockjwales@wikimedia.org',
		];
		$request = [
			"payment_token" => "fake-valid-nonce",
			"order_id" => '123.3',
			"amount" => '1.00',
			"device_data" => '{}',
			'first_name' => $customer['firstName'],
			'last_name' => $customer['lastName'],
			'email' => $customer['email'],
			'phone' => $customer['phoneNumber'],
			"currency" => 'USD'
		];
		$this->api->expects( $this->once() )
			->method( 'authorizePaymentMethod' )
			->willReturn( [
					'errors' => [
						[
							'message' => "Validation error of type FieldUndefined: Field 'user_name' in type 'PayPalAccountDetails' is undefined @ 'authorizePaymentMethod/transaction/paymentMethodSnapshot/payer/user_name'",
							'locations' => [
								[
									'line' => 31,
									'columen' => 13
								]
							]
						]
					],
				'extensions' => [
					'requestId' => 'ffea98e0-29d5-49d1-a9fd-ad316192a59c'
			] ] );

		$provider = new VenmoPaymentProvider();
		$response = $provider->createPayment( $request );
		$this->assertEquals( FinalStatus::FAILED, $response->getStatus() );
		$this->assertNull( $response->getDonorDetails() );
		$this->assertTrue( $response->hasErrors() );
		$this->assertCount( 1, $response->getErrors() );
	}

	public function testApprovePayment() {
		$txn_id = "dHJhbnNhY3Rpb25fYXIxMTNuZzQ";
		$request = [
			"gateway_txn_id" => $txn_id,
		];
		$this->api->expects( $this->once() )
			->method( 'captureTransaction' )
			->willReturn( [
				'data' => [
					'captureTransaction' => [
						'transaction' => [
							'id' => $txn_id,
							'status' => "SUBMITTED_FOR_SETTLEMENT",
						]
					] ],
				'extensions' => [
					'requestId' => 'ffea98e0-29d5-49d1-a9fd-ad316192a59c'
				]
			] );

		$provider = new VenmoPaymentProvider();
		$response = $provider->approvePayment( $request );
		$this->assertEquals( FinalStatus::COMPLETE, $response->getStatus() );
		$this->assertEquals( $txn_id, $response->getGatewayTxnId() );
	}

	public function testRemoveRecurringPaymentToken() {
		$provider = new VenmoPaymentProvider();
		$params = [
			"email" => 'joe@example.com',
			"recurring_payment_token" => '111adsfasdfa23qaser32',
			"processor_contact_id" => '12342134',
			"order_id" => '123.3',
		];
		$this->api->expects( $this->once() )
			->method( 'deletePaymentMethodFromVault' )
			->willReturn( [
				'data' => [
					'deletePaymentMethodFromVault' => [
						'clientMutationId' => $params['recurring_payment_token']
					]
				]
			] );
		$this->api->expects( $this->once() )
			->method( 'deleteCustomer' )
			->willReturn( [
				'data' => [
					'deleteCustomer' => [
						'clientMutationId' => $params['processor_contact_id']
					]
				]
			] );
		$response = $provider->deleteRecurringPaymentToken( $params );
		$this->assertTrue( $response );
	}
}
