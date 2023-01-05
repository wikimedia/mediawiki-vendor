<?php
namespace SmashPig\PaymentProviders\AstroPay\Test;

use SmashPig\PaymentProviders\AstroPay\ExpatriatedMessages\PaymentMessage;
use SmashPig\Tests\BaseSmashPigUnitTestCase;

/**
 * @group AstroPay
 */
class NormalizeTest extends BaseSmashPigUnitTestCase {
	/**
	 * @var array
	 */
	protected $paymentSuccess;

	public function setUp() : void {
		parent::setUp();
		$this->paymentSuccess = $this->loadJson( __DIR__ . "/../Data/paid.json" );
	}

	/**
	 * Check that we produce the right message, including the completion id
	 */
	public function testNormalizePaymentSuccess() {
		$expected = [
			'completion_message_id' => 'astropay-32303.1',
			'contribution_tracking_id' => '32303',
			'currency' => 'BRL',
			'gateway' => 'astropay',
			'gateway_status' => '9',
			'gateway_txn_id' => '31912',
			'gross' => '100.00',
			'order_id' => '32303.1',
		];
		$message = new PaymentMessage();
		$message->constructFromValues( $this->paymentSuccess );
		$normalized = $message->normalizeForQueue();
		unset( $normalized['date'] );
		$this->assertEquals( $expected, $normalized );
	}
}
