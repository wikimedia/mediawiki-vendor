<?php

namespace SmashPig\Tests;

use Exception;
use PDO;
use PHPQueue\Interfaces\FifoQueueStore;
use SmashPig\Core\DataStores\DamagedDatabase;
use SmashPig\Core\DataStores\QueueWrapper;

class QueueConsumerTest extends BaseSmashPigUnitTestCase {

	/**
	 * @var FifoQueueStore
	 */
	protected $queue;
	/**
	 * @var PDO
	 */
	protected $damaged;

	public function setUp(): void {
		parent::setUp();
		$this->queue = QueueWrapper::getQueue( 'test' );
		$damagedDb = DamagedDatabase::get();
		$this->damaged = $damagedDb->getDatabase();
	}

	public function testEmptyQueue() {
		$consumer = new TestingQueueConsumer( 'test' );
		$count = $consumer->dequeueMessages();
		$this->assertSame( 0, $count, 'Should report 0 messages processed' );
	}

	public function testOneMessage() {
		$consumer = new TestingQueueConsumer( 'test' );
		$payload = [
			'wednesday' => 'addams',
			'spookiness' => mt_rand(),
		];
		$this->queue->push( $payload );
		$count = $consumer->dequeueMessages();
		$this->assertSame( 1, $count, 'Should report 1 message processed' );
		$this->assertEquals( [ $payload ], $consumer->processed, 'Bad message' );
		$this->assertNull( $this->queue->pop(),
			'Should delete message when processing is successful'
		);
	}

	public function testDamagedQueue() {
		$payload = [
			'gateway' => 'test',
			'date' => time(),
			'order_id' => mt_rand(),
			'cousin' => 'itt',
			'kookiness' => mt_rand(),
		];

		$consumer = new TestingQueueConsumer( 'test' );
		$consumer->exception = new Exception( 'kaboom!' );

		$this->queue->push( $payload );
		try {
			$consumer->dequeueMessages();
		} catch ( Exception $ex ) {
			$this->fail(
				'Exception should not have bubbled up: ' . $ex->getMessage()
			);
		}
		$this->assertEquals(
			[ $payload ],
			$consumer->processed,
			'Processing snafu'
		);

		$damaged = $this->getDamagedQueueMessage( $payload );
		$this->assertEquals(
			$payload,
			$damaged,
			'Should move message to damaged queue when exception is thrown'
		);
		$this->assertNull(
			$this->queue->pop(),
			'Should delete message on exception when damaged queue exists'
		);
	}

	public function testMessageLimit() {
		$messages = [];
		for ( $i = 0; $i < 5; $i++ ) {
			$message = [
				'gateway' => 'test',
				'date' => time(),
				'order_id' => mt_rand(),
				'box' => 'thing' . $i,
				'creepiness' => mt_rand(),
			];
			$messages[] = $message;
			$this->queue->push( $message );
		}
		// Should work when you pass in the limits as strings.
		$consumer = new TestingQueueConsumer( 'test', 0, '3' );
		$count = $consumer->dequeueMessages();
		$this->assertEquals(
			3, $count, 'dequeueMessages returned wrong count'
		);
		$this->assertCount(
			3,
			$consumer->processed,
			'Called callback wrong number of times'
		);

		for ( $i = 0; $i < 3; $i++ ) {
			$this->assertEquals(
				$messages[$i],
				$consumer->processed[$i],
				'Message mutated'
			);
		}
		$this->assertEquals(
			$messages[3],
			$this->queue->pop(),
			'Dequeued too many messages'
		);
	}

	public function testKeepRunningOnDamage() {
		$messages = [];
		for ( $i = 0; $i < 5; $i++ ) {
			$message = [
				'gateway' => 'test',
				'date' => time(),
				'order_id' => mt_rand(),
				'box' => 'thing' . $i,
				'creepiness' => mt_rand(),
			];
			$messages[] = $message;
			$this->queue->push( $message );
		}

		$consumer = new TestingQueueConsumer( 'test', 0, 3 );
		$consumer->exception = new Exception( 'Kaboom!' );
		$count = 0;
		try {
			$count = $consumer->dequeueMessages();
		} catch ( Exception $ex ) {
			$this->fail(
				'Exception should not have bubbled up: ' . $ex->getMessage()
			);
		}
		$this->assertEquals(
			3, $count, 'dequeueMessages returned wrong count'
		);
		$this->assertCount(
			3,
			$consumer->processed,
			'Called callback wrong number of times'
		);

		for ( $i = 0; $i < 3; $i++ ) {
			$this->assertEquals(
				$messages[$i], $consumer->processed[$i], 'Message mutated'
			);
			$damaged = $this->getDamagedQueueMessage( $messages[$i] );
			$this->assertEquals(
				$messages[$i],
				$damaged,
				'Should move message to damaged queue when exception is thrown'
			);
		}
		$this->assertEquals(
			$messages[3],
			$this->queue->pop(),
			'message 4 should be at the head of the queue'
		);
	}

	protected function getDamagedQueueMessage( $message ) {
		$select = $this->damaged->query( "
			SELECT * FROM damaged
			WHERE gateway='{$message['gateway']}'
			AND order_id = '{$message['order_id']}'" );
		$msg = $select->fetch( PDO::FETCH_ASSOC );
		if ( $msg ) {
			return json_decode( $msg['message'], true );
		}
		return null;
	}

}
