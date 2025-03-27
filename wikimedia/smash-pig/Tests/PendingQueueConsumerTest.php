<?php

namespace SmashPig\Tests;

use SmashPig\Core\DataStores\PaymentsInitialDatabase;
use SmashPig\Core\DataStores\PendingDatabase;
use SmashPig\Core\QueueConsumers\PendingQueueConsumer;
use SmashPig\PaymentData\FinalStatus;
use SmashPig\PaymentData\ValidationAction;

class PendingQueueConsumerTest extends BaseSmashPigUnitTestCase {

	/**
	 * @var PendingDatabase
	 */
	protected $pendingDb;

	/**
	 * @var PaymentsInitialDatabase
	 */
	protected $paymentsInitialDb;

	public function setUp(): void {
		parent::setUp();
		$this->pendingDb = PendingDatabase::get();
		$this->paymentsInitialDb = PaymentsInitialDatabase::get();
	}

	/**
	 * We consume a message normally if there's nothing in the payments_initial
	 * table.
	 */
	public function testPendingMessageNotInInitial() {
		$consumer = new PendingQueueConsumer( 'pending', 1000, 1000, false );
		$message = self::generateRandomPendingMessage();

		$consumer->processMessage( $message );

		$fetched = $this->pendingDb->fetchMessageByGatewayOrderId(
			$message['gateway'], $message['order_id'] );

		$this->assertNotNull( $fetched,
			'Message was consumed and stored in the pending database.' );

		unset( $fetched['pending_id'] );
		$this->assertEquals( $message, $fetched,
			'Stored message is equal to the consumed message.' );
	}

	/**
	 * We consume a message normally if the corresponding payments_initial row
	 * is still pending.
	 */
	public function testPendingMessageInitialPending() {
		$initRow = PaymentsInitialDatabaseTest::generateTestMessage();
		$initRow['payments_final_status'] = 'pending';

		$this->paymentsInitialDb->storeMessage( $initRow );

		$message = self::generatePendingMessageFromInitial( $initRow );
		$consumer = new PendingQueueConsumer( 'pending', 1000, 1000, false );

		$consumer->processMessage( $message );

		$fetched = $this->pendingDb->fetchMessageByGatewayOrderId(
			$message['gateway'], $message['order_id'] );

		$this->assertNotNull( $fetched,
			'Message was consumed and stored in the pending database.' );

		unset( $fetched['pending_id'] );
		$this->assertEquals( $message, $fetched,
			'Stored message is equal to the consumed message.' );
	}

	/**
	 * We refuse to consume a message and drop it if the corresponding
	 * payments_initial row is failed.
	 */
	public function testPendingMessageInitialFailed() {
		$initRow = PaymentsInitialDatabaseTest::generateTestMessage();
		$initRow['payments_final_status'] = FinalStatus::FAILED;
		$initRow['validation_action'] = ValidationAction::REJECT;

		$this->paymentsInitialDb->storeMessage( $initRow );

		$message = self::generatePendingMessageFromInitial( $initRow );
		$consumer = new PendingQueueConsumer( 'pending', 1000, 1000, false );

		$consumer->processMessage( $message );

		$fetched = $this->pendingDb->fetchMessageByGatewayOrderId(
			$message['gateway'], $message['order_id'] );

		$this->assertNull( $fetched,
			'Message consumed and not stored in the pending database.' );
	}

	public static function generateRandomPendingMessage() {
		$message = [
			'gateway' => 'test',
			'date' => time(),
			'order_id' => mt_rand(),
			'cousin' => 'itt',
			'kookiness' => mt_rand(),
		];
		return $message;
	}

	/**
	 * Create an incoming pending message corresponding to a given
	 * payments_initial row.
	 *
	 * @param array $initialRow
	 * @return array Message suitable for the pending queue.
	 */
	public static function generatePendingMessageFromInitial( $initialRow ) {
		$message = [
			'gateway' => $initialRow['gateway'],
			'date' => $initialRow['date'],
			'order_id' => $initialRow['order_id'],
			'cousin' => 'itt',
			'kookiness' => mt_rand(),
		];
		return $message;
	}
}
