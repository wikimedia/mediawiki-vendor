<?php

namespace SmashPig\PaymentProviders\Amazon\Tests;

use SmashPig\PaymentProviders\Amazon\ExpatriatedMessages\CaptureCompleted;
use SmashPig\PaymentProviders\Amazon\ExpatriatedMessages\RefundCompleted;
use SmashPig\Tests\BaseSmashPigUnitTestCase;

/**
 * @group Amazon
 */
class NormalizeTest extends BaseSmashPigUnitTestCase {
	/**
	 * @var CaptureCompleted
	 */
	protected $captureCompleted;

	/**
	 * @var RefundCompleted
	 */
	protected $refundCompleted;

	public function setUp(): void {
		parent::setUp();
		$this->captureCompleted = $this->loadJson( __DIR__ . "/../Data/IPN/CaptureCompleted.json" );
		$this->refundCompleted = $this->loadJson( __DIR__ . "/../Data/IPN/RefundCompleted.json" );
	}

	public function testNormalizeCaptureCompleted() {
		$expected = [
			'class' => '\SmashPig\PaymentProviders\Amazon\RecordPaymentJob',
			'payload' => [
				'contribution_tracking_id' => '98765432',
				'currency' => 'USD',
				'date' => 1357002061,
				'fee' => '0.0',
				'gateway' => 'amazon',
				'gateway_status' => 'Completed',
				'gateway_txn_id' => 'P01-0000000-0000000-000000',
				'gross' => '10.0',
				'order_id' => '98765432-1',
				'payment_method' => 'amazon',
				'order_reference_id' => 'P01-0000000-0000000',
			]
		];
		$message = new CaptureCompleted( $this->captureCompleted );
		$normalized = $message->normalizeForQueue();
		$this->assertEquals( $expected, $normalized );
	}

	public function testNormalizeRefundCompleted() {
		$id = 'P01-0000000-0000000-C' . mt_rand( 10000, 99999 );
		$expected = [
			'gross_currency' => 'USD',
			'date' => 1357002061,
			'gateway' => 'amazon',
			'gateway_parent_id' => $id,
			'gateway_refund_id' => 'P01-0000000-0000000-R00000',
			'gross' => '10.0',
			'type' => 'refund',
		];
		$message = new RefundCompleted( $this->refundCompleted );
		$message->setParentId( $id );
		$normalized = $message->normalizeForQueue();
		$this->assertEquals( $expected, $normalized );
	}
}
