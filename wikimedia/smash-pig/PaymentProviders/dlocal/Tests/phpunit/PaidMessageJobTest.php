<?php

namespace SmashPig\PaymentProviders\dlocal\Tests;

use SmashPig\Core\DataStores\QueueWrapper;
use SmashPig\Core\QueueConsumers\JobQueueConsumer;
use SmashPig\PaymentProviders\dlocal\ExpatriatedMessages\PaidMessage;
use SmashPig\Tests\BaseSmashPigUnitTestCase;

/**
 * @group Dlocal
 */
class PaidMessageJobTest extends BaseSmashPigUnitTestCase {

	/**
	 * Test that Paid IPNs for one-time donations are pushed to the 'donations' queue
	 */
	public function testIndiaPaidOneTimeMessagePushedToDonationsQueue(): void {
		// set up the test PaidMessage object
		$paidIpnFixture = json_decode( file_get_contents( __DIR__ . '/../Data/paid-ipn-india-one-time.json' ), true );
		$paidMessage = new PaidMessage();
		$paidMessage->constructFromValues(
			$paidIpnFixture
		);

		// set up the PaidMessageJob using the PaidMessage test
		$paidMessageJob = JobQueueConsumer::createJobObject(
			$paidMessage->normalizeForQueue()
		);

		// check that job completes successfully
		$this->assertTrue( $paidMessageJob->execute() );

		// check that a message is pushed to donations queue
		$donationsQueueMessage = QueueWrapper::getQueue( 'donations' )->pop();
		$this->assertNotNull( $donationsQueueMessage, 'donations queue should not be empty!' );
	}

	/**
	 * Test that Paid IPNs for recurring donations are pushed to the 'upi-donations' queue
	 */
	public function testIndiaPaidRecurringMessagePushedToUpiDonationsQueue(): void {
		// set up our test PaidMessage object
		$paidIpnFixture = json_decode( file_get_contents( __DIR__ . '/../Data/paid-ipn-india-recurring.json' ), true );
		$paidMessage = new PaidMessage();
		$paidMessage->constructFromValues(
			$paidIpnFixture
		);

		// set up our PaidMessageJob using the PaidMessage test
		$paidMessageJob = JobQueueConsumer::createJobObject(
			$paidMessage->normalizeForQueue()
		);

		// check that job completes successfully
		$this->assertTrue( $paidMessageJob->execute() );

		// check that a message is pushed to upi-donations queue
		$upiDonationsQueueMessage = QueueWrapper::getQueue( 'upi-donations' )->pop();
		$this->assertNotNull( $upiDonationsQueueMessage, 'upi-donations queue should not be empty!' );
	}

}
