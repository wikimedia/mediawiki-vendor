<?php

namespace SmashPig\Tests;

use SmashPig\Core\DataStores\PaymentsFraudDatabase;
use SmashPig\Core\UtcDate;

class PaymentsFraudDatabaseTest extends BaseSmashPigUnitTestCase {

	/**
	 * @var PaymentsFraudDatabase
	 */
	protected $db;

	public function setUp() : void {
		parent::setUp();
		$this->db = PaymentsFraudDatabase::get();
	}

	public static function generateTestMessage() {
		$message = [
			'contribution_tracking_id' => mt_rand(),
			'gateway' => 'test_gateway',
			'order_id' => mt_rand(),
			'validation_action' => 'process',
			'user_ip' => '123.45.67.89',
			'payment_method' => 'cc',
			'risk_score' => 42.3,
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

		unset( $fetched['id'] );
		// date is converted to unix timestamp when retrieved.
		$message['date'] = strtotime( $message['date'] );

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

	/**
	 * Test we can store and fetch a message with a score_breakdown.
	 */
	public function testFetchMessageByGatewayOrderIdWithBreakdown() {
		$message = self::generateTestMessage();
		$message['score_breakdown'] = [
			'getCVVResult' => 20,
			'getAVSResult' => 10,
			'getScoreCountryMap' => 8,
			'minfraud_filter' => 4.3
		];
		$this->db->storeMessage( $message );

		$fetched = $this->db->fetchMessageByGatewayOrderId(
			'test_gateway', $message['order_id'], true );
		$this->assertNotNull( $fetched,
			'Record retrieved by fetchMessageByGatewayOrderId.' );

		unset( $fetched['id'] );
		// date is converted to unix timestamp when retrieved.
		$message['date'] = strtotime( $message['date'] );

		$this->assertEquals( $message, $fetched,
			'Fetched record matches stored message.' );
	}

	public function testUpdateMessageNoBreakdown() {
		$message = self::generateTestMessage();
		$initialId = $this->db->storeMessage( $message );
		$message['risk_score'] = 99;
		$message['validation_action'] = 'review';
		$updateId = $this->db->storeMessage( $message );
		$this->assertEquals( $initialId, $updateId, 'Row got new ID on update' );
		$fetched = $this->db->fetchMessageByGatewayOrderId(
			'test_gateway', $message['order_id'], true );
		$this->assertEquals( 99, $fetched['risk_score'] );
		$this->assertEquals( 'review', $fetched['validation_action'] );
	}

	public function testUpdateMessageAddBreakdownRowsNoExisting() {
		$message = self::generateTestMessage();
		$this->db->storeMessage( $message );
		$message['score_breakdown'] = [
			'getCVVResult' => 20,
			'getAVSResult' => 10,
			'getScoreCountryMap' => 8,
			'minfraud_filter' => 4.3
		];
		$this->db->storeMessage( $message );
		$fetched = $this->db->fetchMessageByGatewayOrderId(
			'test_gateway', $message['order_id'], true );
		$this->assertEquals(
			$message['score_breakdown'],
			$fetched['score_breakdown']
		);
	}

	public function testUpdateMessageUpdateBreakdownRows() {
		$message = self::generateTestMessage();
		$message['score_breakdown'] = [
			'getCVVResult' => 20,
			'getAVSResult' => 10,
			'getScoreCountryMap' => 8,
			'minfraud_filter' => 4.3
		];
		$this->db->storeMessage( $message );
		$message['score_breakdown'] = [
			'getCVVResult' => 30,
			'getAVSResult' => 20,
			'getScoreCountryMap' => 18,
			'minfraud_filter' => 2.3
		];
		$this->db->storeMessage( $message );
		$fetched = $this->db->fetchMessageByGatewayOrderId(
			'test_gateway', $message['order_id'], true );
		$this->assertEquals(
			$message['score_breakdown'],
			$fetched['score_breakdown']
		);
	}

	public function testUpdateMessageAddAndUpdateBreakdownRows() {
		$message = self::generateTestMessage();
		$message['score_breakdown'] = [
			'getScoreCountryMap' => 8,
			'minfraud_filter' => 4.3
		];
		$this->db->storeMessage( $message );
		$message['score_breakdown'] = [
			'getCVVResult' => 30,
			'getAVSResult' => 20,
			'getScoreCountryMap' => 18
		];
		$this->db->storeMessage( $message );
		$fetched = $this->db->fetchMessageByGatewayOrderId(
			'test_gateway', $message['order_id'], true );
		$this->assertEquals(
			[
				'getCVVResult' => 30,
				'getAVSResult' => 20,
				'getScoreCountryMap' => 18,
				'minfraud_filter' => 4.3
			],
			$fetched['score_breakdown']
		);
	}
}
