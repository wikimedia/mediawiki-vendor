<?php

namespace SmashPig\Tests;

use SmashPig\Core\DataStores\DamagedDatabase;
use SmashPig\Core\DataStores\QueueWrapper;
use SmashPig\Core\QueueConsumers\QueueFileDumper;

class FileDumperTest extends BaseSmashPigUnitTestCase {

	protected $filename;

	public function setUp(): void {
		parent::setUp();
		$this->filename = tempnam( '/tmp', 'sptest' );
	}

	public function tearDown(): void {
		parent::tearDown();
		if ( file_exists( $this->filename ) ) {
			unlink( $this->filename );
		}
	}

	public function testDump() {
		$queue = QueueWrapper::getQueue( 'test' );
		$expected = '';
		for ( $i = 0; $i < 5; $i++ ) {
			$message = [
				'psycho' => 'alpha',
				'disco' => 'beta',
				'bio' => 'aqua',
				'dooloop' => mt_rand()
			];
			$queue->push( $message );
			$expected .= json_encode( $message ) . "\n";
		}
		$dumper = new QueueFileDumper( 'test', 0, $this->filename );
		$dumper->dequeueMessages();
		$this->assertEquals( $expected, file_get_contents( $this->filename ) );
	}

	public function testDumpWithConditions() {
		$queue = QueueWrapper::getQueue( 'test' );
		$expected = '';
		$expectedRetry = [];
		for ( $i = 0; $i < 6; $i++ ) {
			$message = [
				'psycho' => 'alpha',
				'disco' => 'beta',
				'bio' => 'aqua',
				'dooloop' => mt_rand()
			];
			if ( $i % 2 == 0 ) {
				$message['discriminator'] = 'floober';
				$expected .= json_encode( $message ) . "\n";
			} else {
				$expectedRetry[] = $message;
			}
			$queue->push( $message );
		}
		$dumper = new QueueFileDumper(
			'test', 0, $this->filename, [ 'discriminator' => 'floober' ]
		);
		$dumper->dequeueMessages();
		$this->assertEquals( $expected, file_get_contents( $this->filename ) );

		$retry = DamagedDatabase::get()->fetchRetryMessages( 100 );
		foreach ( $retry as &$actualMessage ) {
			unset( $actualMessage['damaged_id'] );
			unset( $actualMessage['original_queue'] );
		}
		$this->assertEquals( $expectedRetry, $retry );
	}
}
