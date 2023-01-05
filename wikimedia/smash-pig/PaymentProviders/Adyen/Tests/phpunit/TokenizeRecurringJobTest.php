<?php

namespace SmashPig\PaymentProviders\Adyen\Tests;

use SmashPig\Core\DataStores\QueueWrapper;
use SmashPig\Core\QueueConsumers\JobQueueConsumer;
use SmashPig\CrmLink\Messages\SourceFields;
use SmashPig\PaymentData\SavedPaymentDetails;
use SmashPig\PaymentProviders\Adyen\CardPaymentProvider;
use SmashPig\PaymentProviders\Adyen\TokenizeRecurringJob;
use SmashPig\PaymentProviders\Responses\SavedPaymentDetailsResponse;
use SmashPig\Tests\BaseSmashPigUnitTestCase;

/**
 * @group Adyen
 */
class TokenizeRecurringJobTest extends BaseSmashPigUnitTestCase {

	/**
	 * @var CardPaymentProvider
	 */
	protected $provider;

	public function setUp() : void {
		parent::setUp();

		$providerConfiguration = $this->setProviderConfiguration( 'adyen' );
		$this->provider = $this->createMock( '\SmashPig\PaymentProviders\Adyen\CardPaymentProvider' );
		$providerConfiguration->overrideObjectInstance( 'payment-provider/cc', $this->provider );
	}

	/**
	 * @dataProvider donationMessageProvider
	 * @param $message
	 */
	public function testFromDonationMessage( $message ) {
		$expected = [
			'class' => '\SmashPig\PaymentProviders\Adyen\TokenizeRecurringJob',
			'payload' => $message
		];
		$actual = TokenizeRecurringJob::fromDonationMessage( $message );
		$this->assertEquals( $expected, $actual );
	}

	public function donationMessageProvider() {
		return [
			[ // Does not need tokenizing b/c it's not recurring
				[
					'gateway' => 'adyen',
					'recurring' => 0,
					'gross' => 10,
					'currency' => 'USD',
					'gateway_txn_id' => 'CPBGBZ9Z63RZNN82',
				],
				false,
			],
			[ // Does not need tokenizing b/c it's got a token
				[
					'gateway' => 'adyen',
					'recurring' => 1,
					'gross' => 10,
					'currency' => 'USD',
					'gateway_txn_id' => 'CPBGBZ9Z63RZNN82',
					'recurring_payment_token' => 'VL4VZ779Z2M84H82',
				],
				false,
			],
			[ // Needs tokenizing
				[
					'gateway' => 'adyen',
					'recurring' => 1,
					'gross' => 10,
					'currency' => 'USD',
					'gateway_txn_id' => 'CPBGBZ9Z63RZNN82'
				],
				true,
			],
		];
	}

	/**
	 * @dataProvider donationMessageProvider
	 * @param array $message
	 * @param boolean $expectedNeedsTokenizing
	 */
	public function testNeedsTokenizing( $message, $expectedNeedsTokenizing ) {
		$actual = TokenizeRecurringJob::donationNeedsTokenizing( $message );
		$this->assertEquals( $expectedNeedsTokenizing, $actual );
	}

	public function testExecute() {
		$job = new TokenizeRecurringJob();
		$job->payload = [
			'gateway' => 'adyen',
			'recurring' => 1,
			'gross' => 10,
			'currency' => 'USD',
			'processor_contact_id' => '3.1',
			'gateway_txn_id' => 'CPBGBZ9Z63RZNN82',
		];
		$savedDetails = ( new SavedPaymentDetails() )->setToken( 'VL4VZ779Z2M84H82' );
		$detailsResponse = ( new SavedPaymentDetailsResponse() )->setDetailsList( [ $savedDetails ] );
		$this->provider->expects( $this->once() )
			->method( 'getSavedPaymentDetails' )
			->with(
				$this->equalTo( '3.1' )
			)->willReturn( $detailsResponse );
		$job->execute();
		$queue = QueueWrapper::getQueue( 'donations' );
		$message = $queue->pop();
		$expected = $job->payload + [
				'recurring_payment_token' => 'VL4VZ779Z2M84H82'
			];
		SourceFields::removeFromMessage( $message );
		$this->assertEquals( $expected, $message );
	}

	public function testDeserialize() {
		$originalMessage = [
			'gateway' => 'adyen',
			'recurring' => 1,
			'gross' => 10,
			'currency' => 'USD',
			'processor_contact_id' => '3.1',
			'gateway_txn_id' => 'CPBGBZ9Z63RZNN82',
		];
		$job = TokenizeRecurringJob::fromDonationMessage( $originalMessage );
		QueueWrapper::push( 'jobs-adyen', $job );

		$savedDetails = ( new SavedPaymentDetails() )->setToken( 'VL4VZ779Z2M84H82' );
		$detailsResponse = ( new SavedPaymentDetailsResponse() )->setDetailsList( [ $savedDetails ] );
		$this->provider->expects( $this->once() )
			->method( 'getSavedPaymentDetails' )
			->with(
				$this->equalTo( '3.1' )
			)->willReturn( $detailsResponse );

		$runner = new JobQueueConsumer( 'jobs-adyen' );
		$runner->dequeueMessages();

		$queue = QueueWrapper::getQueue( 'donations' );
		$resultMessage = $queue->pop();
		$expected = $originalMessage + [
				'recurring_payment_token' => 'VL4VZ779Z2M84H82'
			];
		SourceFields::removeFromMessage( $resultMessage );

		$this->assertEquals( $expected, $resultMessage );
	}
}
