<?php

namespace SmashPig\Tests;

use PDO;
use SmashPig\Core\DataStores\DamagedDatabase;

class DamagedDatabaseTest extends BaseSmashPigUnitTestCase {

	/**
	 * @var DamagedDatabase
	 */
	protected $db;

	public function setUp(): void {
		parent::setUp();
		$this->db = DamagedDatabase::get();
	}

	protected static function getTestMessage( $uniq = null ) {
		if ( !$uniq ) {
			$uniq = mt_rand();
		}
		return [
			'gateway' => 'test',
			'gateway_txn_id' => "txn-{$uniq}",
			'order_id' => "order-{$uniq}",
			'gateway_account' => 'default',
			'date' => 1468973648,
			'amount' => 123,
			'currency' => 'EUR',
		];
	}

	public function testStoreMessage() {
		$message = self::getTestMessage();
		$queue = 'test_queue';
		$err = 'ERROR MESSAGE';
		$trace = "Foo.php line 25\nBar.php line 99";

		$damagedId = $this->db->storeMessage( $message, $queue, $err, $trace );

		// Confirm work without using the API.
		$pdo = $this->db->getDatabase();
		$result = $pdo->query( "
			SELECT * FROM damaged
			WHERE gateway='test'
			AND order_id = '{$message['order_id']}'" );
		$rows = $result->fetchAll( PDO::FETCH_ASSOC );
		$this->assertCount( 1, $rows,
			'One row stored and retrieved.' );
		$expected = [
			'id' => $damagedId,
			// NOTE: This is a db-specific string, sqlite3 in this case, and
			// you'll have different formatting if using any other database.
			'original_date' => '20160720001408',
			'gateway' => 'test',
			'order_id' => $message['order_id'],
			'gateway_txn_id' => $message['gateway_txn_id'],
			'message' => json_encode( $message ),
			'original_queue' => $queue,
			'error' => $err,
			'trace' => $trace,
			'retry_date' => null,
		];
		unset( $rows[0]['damaged_date'] );
		$this->assertEquals( $expected, $rows[0],
			'Stored message had expected contents' );
	}

	public function testFetchRetryMessages() {
		$message = self::getTestMessage();
		$this->db->storeMessage( $message, 'test_queue', '', '', time() - 1 );

		$fetched = $this->db->fetchRetryMessages( 10 );

		$this->assertNotNull( $fetched,
			'No record retrieved by fetchRetryMessages.' );

		$expected = $message + [
			'damaged_id' => 1,
			'original_queue' => 'test_queue'
		];
		$this->assertEquals( $expected, $fetched[0],
			'Fetched record does not matches stored message.' );
	}

	public function testDeleteMessage() {
		$uniq = mt_rand();
		$queue = 'test_queue';
		$message1 = $this->getTestMessage( $uniq );
		// Store a second message for a good time, and make sure we delete the
		// right one.
		$message2 = $this->getTestMessage( $uniq );

		$this->db->storeMessage( $message1, $queue );
		// store message 2 with a
		$this->db->storeMessage( $message2, $queue );

		// Confirm work without using the API.
		$pdo = $this->db->getDatabase();
		$result = $pdo->query( "
			SELECT * FROM damaged
			WHERE gateway='test'
				AND order_id = '{$message1['order_id']}'" );
		$rows = $result->fetchAll( PDO::FETCH_ASSOC );
		$this->assertCount( 2, $rows,
			'Both records were stored.' );
		$this->assertNotNull( $rows[0]['id'],
			'Record includes a primary row id' );
		$this->assertNotEquals( $rows[0]['id'], $rows[1]['id'],
			'Records have unique primary ids' );

		$message2['damaged_id'] = $rows[1]['id'];
		$this->db->deleteMessage( $message2 );

		// Confirm work without using the API.
		$pdo = $this->db->getDatabase();
		$result = $pdo->query( "
			SELECT * FROM damaged
			WHERE gateway='test'
				AND order_id = '{$message1['order_id']}'" );
		$rowsAfter = $result->fetchAll( PDO::FETCH_ASSOC );
		$this->assertCount( 1, $rowsAfter,
			'Not only one row deleted.' );
		$this->assertEquals( $rowsAfter[0]['id'], $rows[0]['id'],
			'Deleted the wrong row.' );
	}
}
