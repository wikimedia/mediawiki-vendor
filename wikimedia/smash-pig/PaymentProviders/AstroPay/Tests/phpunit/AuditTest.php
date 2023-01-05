<?php
namespace SmashPig\PaymentProviders\AstroPay\Test;

use SmashPig\PaymentProviders\AstroPay\Audit\AstroPayAudit;
use SmashPig\Tests\BaseSmashPigUnitTestCase;

/**
 * Verify AstroPay audit file processor functions
 *
 * @group Audit
 * @group AstroPay
 */
class AuditTest extends BaseSmashPigUnitTestCase {
	/**
	 * Normal donation
	 */
	public function testProcessDonation() {
		$processor = new AstroPayAudit();
		$output = $processor->parseFile( __DIR__ . '/../Data/donation.csv' );
		$this->assertSame( 1, count( $output ), 'Should have found one donation' );
		$actual = $output[0];
		$expected = [
			'gateway' => 'astropay',
			'date' => 1434450583,
			'gross' => '5.00',
			'contribution_tracking_id' => '266221341',
			'country' => 'BR',
			'currency' => 'BRL',
			'email' => 'donoriffic@example.org',
			'gateway_txn_id' => '5432123',
			'invoice_id' => '266221341.0',
			'payment_method' => 'cc',
			'payment_submethod' => 'mc',
			'settled_date' => 1434456032,
			'settled_currency' => 'USD',
			'settled_fee' => '0.03',
			'settled_gross' => '1.50',
		];
		$this->assertEquals( $expected, $actual, 'Did not parse donation correctly' );
	}

	/**
	 * Now try a refund
	 */
	public function testProcessRefund() {
		$processor = new AstroPayAudit();
		$output = $processor->parseFile( __DIR__ . '/../Data/refund.csv' );
		$this->assertSame( 1, count( $output ), 'Should have found one refund' );
		$actual = $output[0];
		$expected = [
			'gateway' => 'astropay',
			'contribution_tracking_id' => '314159265',
			'date' => 1434747909,
			'gross' => '5.00',
			'gateway_parent_id' => '7654321',
			'gateway_refund_id' => '12345',
			'gross_currency' => 'BRL',
			'invoice_id' => '314159265.0',
			'type' => 'refund',
		];
		$this->assertEquals( $expected, $actual, 'Did not parse refund correctly' );
	}

	/**
	 * And a chargeback
	 */
	public function testProcessChargeback() {
		$processor = new AstroPayAudit();
		$output = $processor->parseFile( __DIR__ . '/../Data/chargebacks.csv' );
		$this->assertSame( 1, count( $output ), 'Should have found one chargeback' );
		$actual = $output[0];
		$expected = [
			'gateway' => 'astropay',
			'contribution_tracking_id' => '314159265',
			'date' => 1434747909,
			'gross' => '5.00',
			'gateway_parent_id' => '7654321',
			'gateway_refund_id' => '12345',
			'gross_currency' => 'BRL',
			'invoice_id' => '314159265.0',
			'type' => 'chargeback',
		];
		$this->assertEquals( $expected, $actual, 'Did not parse chargeback correctly' );
	}
}
