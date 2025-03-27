<?php

namespace SmashPig\Tests;

use SmashPig\Core\DataStores\PaymentsInitialDatabase;
use SmashPig\Core\UtcDate;

class PaymentsInitialDatabaseTest extends BaseSmashPigUnitTestCase {

	/**
	 * @var PaymentsInitialDatabase
	 */
	protected $db;

	public function setUp(): void {
		parent::setUp();
		$this->db = PaymentsInitialDatabase::get();
	}

	public static function generateTestMessage() {
		$message = [
			'id' => mt_rand(),
			'contribution_tracking_id' => mt_rand(),
			'gateway' => 'test_gateway',
			'order_id' => mt_rand(),
			'gateway_txn_id' => mt_rand(),
			'validation_action' => 'process',
			'payments_final_status' => 'complete',
			'payment_method' => 'cc',
			'payment_submethod' => 'jcb',
			'country' => 'FR',
			'amount' => 1.01,
			'currency_code' => 'EUR',
			'server' => 'localhost',
			'date' => UtcDate::getUtcDatabaseString( time() ),
		];
		return $message;
	}

	public function testFetchMessageByGatewayOrderId() {
		$message = self::generateTestMessage();
		$this->db->storeMessage( $message );

		$fetched = $this->db->fetchMessageByGatewayOrderId(
			'test_gateway', $message['order_id'] );
		$this->assertNotNull( $fetched,
			'Record retrieved by fetchMessageByGatewayOrderId.' );

		$this->assertEquals( $message, $fetched,
			'Fetched record matches stored message.' );
	}

	/**
	 * Test that fetchMessageByGatewayOrderId returns null when the message
	 * isn't found.
	 */
	public function testFetchMessageByGatewayOrderIdNone() {
		$message = $this->generateTestMessage();
		$this->db->storeMessage( $message );

		$fetched = $this->db->fetchMessageByGatewayOrderId(
			'test_gateway', $message['order_id'] + 1 );
		$this->assertNull( $fetched,
			'Record correctly not found fetchMessageByGatewayOrderId.' );
	}
}
