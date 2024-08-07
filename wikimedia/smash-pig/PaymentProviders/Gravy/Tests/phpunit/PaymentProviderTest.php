<?php

namespace SmashPig\PaymentProviders\Gravy\Tests\phpunit;

use SmashPig\PaymentProviders\Gravy\PaymentProvider;
use SmashPig\PaymentProviders\Gravy\Tests\BaseGravyTestCase;

/**
 * @group Gravy
 */
class PaymentProviderTest extends BaseGravyTestCase {

	/**
	 * @var PaymentProvider
	 */
	public $provider;

	public function setUp() : void {
		parent::setUp();
		$this->provider = $this->config->object( 'payment-provider/cc' );
	}

	public function testSuccessfulCreateDonor() {
		$responseBody = json_decode( file_get_contents( __DIR__ . '/../Data/create-buyer.json' ), true );
		$this->mockApi->expects( $this->once() )
			->method( 'createDonor' )
			->willReturn( $responseBody );
		$params = $this->getCreateDonorParams();
		$response = $this->provider->createDonor( $params );

		$this->assertInstanceOf( '\SmashPig\PaymentProviders\Responses\PaymentDetailResponse',
			$response );
		$this->assertTrue( $response->isSuccessful() );
		$this->assertEquals( $responseBody['id'], $response->getDonorDetails()->getCustomerId() );
		$this->assertEquals( $params['first_name'], $response->getDonorDetails()->getFirstName() );
		$this->assertEquals( $params['last_name'], $response->getDonorDetails()->getLastName() );
		$this->assertEquals( $responseBody['billing_details']['address']['city'], $response->getDonorDetails()->getBillingAddress()->getCity() );
	}

	public function testValidationErrorCreateDonorBeforeApiCall() {
		$params = [
			'gateway_session_id' => 'random-session-id'
		];

		$response = $this->provider->createDonor( $params );

		$this->assertInstanceOf( '\SmashPig\PaymentProviders\Responses\PaymentDetailResponse',
			$response );
		$this->assertFalse( $response->isSuccessful() );
		$valErrors = $response->getValidationErrors();
		$errors = $response->getErrors();
		$this->assertCount( 3, $valErrors );
		$this->assertCount( 0, $errors );
	}

	public function testSuccessfulGetDonor() {
		$responseBody = json_decode( file_get_contents( __DIR__ . '/../Data/list-buyer.json' ), true );
		$this->mockApi->expects( $this->once() )
			->method( 'getDonor' )
			->willReturn( $responseBody );
		$params = $this->getCreateDonorParams();
		$response = $this->provider->getDonorRecord( $params );

		$this->assertInstanceOf( '\SmashPig\PaymentProviders\Responses\PaymentDetailResponse',
			$response );
		$this->assertTrue( $response->isSuccessful() );
		$donor = $responseBody['items'][0];
		$this->assertEquals( $donor['id'], $response->getDonorDetails()->getCustomerId() );
		$this->assertEquals( $params['first_name'], $response->getDonorDetails()->getFirstName() );
		$this->assertEquals( $params['last_name'], $response->getDonorDetails()->getLastName() );
		$this->assertEquals( $donor['billing_details']['address']['city'], $response->getDonorDetails()->getBillingAddress()->getCity() );
	}

	public function testValidationErrorGetDonorBeforeApiCall() {
		$params = [
			'gateway_session_id' => 'random-session-id'
		];

		$response = $this->provider->getDonorRecord( $params );

		$this->assertInstanceOf( '\SmashPig\PaymentProviders\Responses\PaymentDetailResponse',
			$response );
		$this->assertFalse( $response->isSuccessful() );
		$valErrors = $response->getValidationErrors();
		$errors = $response->getErrors();
		$this->assertCount( 1, $valErrors );
		$this->assertCount( 0, $errors );
	}

	public function testValidationErrorDeletePaymentTokenApiCall() {
		$params = [
			'gateway_session_id' => 'random-session-id'
		];

		$response = $this->provider->deleteRecurringPaymentToken( $params );
		$this->assertFalse( $response );
	}

	public function testSuccessfulDeletePaymentTokenApiCall() {
		$params = [
			'recurring_payment_token' => 'random-payment-token'
		];

		$this->mockApi->expects( $this->once() )
			->method( 'deletePaymentToken' )
			->willReturn( [] );

		$response = $this->provider->deleteRecurringPaymentToken( $params );
		$this->assertTrue( $response );
	}

	public function testErrorDeletePaymentTokenApiCall() {
		$responseBody = json_decode( file_get_contents( __DIR__ . '/../Data/delete-token-error.json' ), true );
		$params = [
			'recurring_payment_token' => 'random-payment-token'
		];

		$this->mockApi->expects( $this->once() )
			->method( 'deletePaymentToken' )
			->willReturn( $responseBody );

		$response = $this->provider->deleteRecurringPaymentToken( $params );
		$this->assertFalse( $response );
	}

	public function testApiErrorDeletePaymentTokenApiCall() {
		$responseBody = json_decode( file_get_contents( __DIR__ . '/../Data/delete-token-api-error.json' ), true );
		$params = [
			'recurring_payment_token' => 'random-payment-token'
		];

		$this->mockApi->expects( $this->once() )
			->method( 'deletePaymentToken' )
			->willReturn( $responseBody );

		$response = $this->provider->deleteRecurringPaymentToken( $params );
		$this->assertFalse( $response );
	}
}
