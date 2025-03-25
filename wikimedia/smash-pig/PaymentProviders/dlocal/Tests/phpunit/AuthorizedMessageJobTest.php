<?php

namespace SmashPig\PaymentProviders\dlocal\Tests;

use SmashPig\Core\DataStores\PendingDatabase;
use SmashPig\Core\QueueConsumers\JobQueueConsumer;
use SmashPig\PaymentProviders\dlocal\ExpatriatedMessages\AuthorizedMessage;
use SmashPig\Tests\BaseSmashPigUnitTestCase;

/**
 * @group Dlocal
 */
class AuthorizedMessageJobTest extends BaseSmashPigUnitTestCase {

	/**
	 * Test that recurring tokens from Authorized IPNs are added to the pending row
	 */
	public function testAuthorizedMessageAddsTokenToPending() : void {
		// Store a pending recurring transaction with no token
		$pendingDb = PendingDatabase::get();
		$pendingDb->storeMessage(
			json_decode( file_get_contents( __DIR__ . '/../Data/authorized-pending-row.json' ), true )
		);

		// Simulate an IPN coming in with the token in the "card" data block
		$authorizedIpnFixture = json_decode( file_get_contents( __DIR__ . '/../Data/authorized.json' ), true );
		$authorizedMessage = new AuthorizedMessage();
		$authorizedMessage->constructFromValues(
			$authorizedIpnFixture
		);

		$authorizedMessageJob = JobQueueConsumer::createJobObject(
			$authorizedMessage->normalizeForQueue()
		);

		// check that job completes successfully
		$this->assertTrue( $authorizedMessageJob->execute() );

		// Check that the token has been added
		$newPendingRecord = $pendingDb->fetchMessageByGatewayOrderId(
			'dlocal', $authorizedIpnFixture['order_id']
		);
		$this->assertEquals( $newPendingRecord['recurring_payment_token'], $authorizedIpnFixture['card']['token'] );
	}
}
