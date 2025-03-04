<?php
namespace SmashPig\Tests;

use SmashPig\CrmLink\Messages\DonationInterfaceAntifraudFactory;

/**
 * Test CrmLink message functions
 */
class MessageTest extends BaseSmashPigUnitTestCase {

	protected function getDiMessage() {
		$ctId = mt_rand( 0, 1000000 );
		return [
			'contribution_tracking_id' => $ctId,
			'date' => 1455128736,
			'gateway' => 'adyen',
			'gateway_txn_id' => mt_rand( 0, 10000000 ),
			'payment_method' => 'cc',
			'user_ip' => '8.8.4.4',
			'order_id' => $ctId . '.0',
		];
	}

	public function testAntifraudFactory() {
		$diMessage = $this->getDiMessage();

		$scoreBreakdown = [
			'getScoreCountry' => 25,
			'getScoreEmailDomain' => 10,
		];
		$afMessage = DonationInterfaceAntifraudFactory::create(
			$diMessage, 12.5, $scoreBreakdown, 'process'
		);

		$this->assertEquals( $diMessage['contribution_tracking_id'], $afMessage['contribution_tracking_id'] );
		$this->assertEquals( 1455128736, $afMessage['date'] );
		$this->assertEquals( 'adyen', $afMessage['gateway'] );
		$this->assertEquals( $diMessage['order_id'], $afMessage['order_id'] );
		$this->assertEquals( 'cc', $afMessage['payment_method'] );
		$this->assertEquals( 12.5, $afMessage['risk_score'] );
		$this->assertEquals( $scoreBreakdown, $afMessage['score_breakdown'] );
		$this->assertEquals( '8.8.4.4', $afMessage['user_ip'] );
		$this->assertEquals( 'process', $afMessage['validation_action'] );
	}

	public function testAntifraudFactoryDateFormat() {
		$diMessage = $this->getDiMessage();
		$diMessage['date'] = '2018-07-12 14:22:02';

		$scoreBreakdown = [
			'getScoreCountry' => 25,
			'getScoreEmailDomain' => 10,
		];
		$afMessage = DonationInterfaceAntifraudFactory::create(
			$diMessage, 12.5, $scoreBreakdown, 'process'
		);

		$this->assertEquals( 1531405322, $afMessage['date'] );
	}
}
