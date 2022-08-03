<?php

namespace SmashPig\PaymentProviders\Adyen\Tests;

use SmashPig\Core\DataStores\QueueWrapper;
use SmashPig\Core\QueueConsumers\JobQueueConsumer;
use SmashPig\CrmLink\Messages\SourceFields;
use SmashPig\PaymentProviders\Adyen\CardPaymentProvider;
use SmashPig\PaymentProviders\Adyen\TokenizeRecurringJob;
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
					'gross' => 1.23,
					'currency' => 'AUD',
					'gateway_txn_id' => '000000123400000004230000100001',
				],
				false,
			],
			[ // Does not need tokenizing b/c it's got a token
				[
					'gateway' => 'adyen',
					'recurring' => 1,
					'gross' => 1.23,
					'currency' => 'USD',
					'gateway_txn_id' => '000000123400000004230000100001',
					'recurring_payment_token' => '2d6f1234-df49-9876-bcb4-55aa44ce3e22',
				],
				false,
			],
			[ // Needs tokenizing
				[
					'gateway' => 'adyen',
					'recurring' => 1,
					'gross' => 1.23,
					'currency' => 'AUD',
					'gateway_txn_id' => '000000123400000004230000100001'
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
			'gross' => 1.23,
			'currency' => 'AUD',
			'gateway_txn_id' => '000000123400000004230000100001'
		];
		$this->provider->expects( $this->once() )
			->method( 'tokenizePayment' )
			->with(
				$this->equalTo( '000000123400000004230000100001' )
			)->willReturn(
				[
					'token' => '2d6f1234-df49-9876-bcb4-55aa44ce3e22'
				]
			);
		$job->execute();
		$queue = QueueWrapper::getQueue( 'donations' );
		$message = $queue->pop();
		$expected = $job->payload + [
				'recurring_payment_token' => '2d6f1234-df49-9876-bcb4-55aa44ce3e22'
			];
		SourceFields::removeFromMessage( $message );
		$this->assertEquals( $expected, $message );
	}

	public function testDeserialize() {
		$originalMessage = [
			'gateway' => 'adyen',
			'recurring' => 1,
			'gross' => 1.23,
			'currency' => 'AUD',
			'gateway_txn_id' => '000000123400000004230000100001',
		];
		$job = TokenizeRecurringJob::fromDonationMessage( $originalMessage );
		QueueWrapper::push( 'jobs-adyen', $job );

		$this->provider->expects( $this->once() )
			->method( 'tokenizePayment' )
			->with(
				$this->equalTo( '000000123400000004230000100001' )
			)->willReturn(
				[
					'token' => '2d6f1234-df49-9876-bcb4-55aa44ce3e22'
				]
			);

		$runner = new JobQueueConsumer( 'jobs-adyen' );
		$runner->dequeueMessages();

		$queue = QueueWrapper::getQueue( 'donations' );
		$resultMessage = $queue->pop();
		$expected = $originalMessage + [
				'recurring_payment_token' => '2d6f1234-df49-9876-bcb4-55aa44ce3e22'
			];
		SourceFields::removeFromMessage( $resultMessage );
		$this->assertEquals( $expected, $resultMessage );
	}
}
