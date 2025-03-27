<?php
namespace SmashPig\PaymentProviders\dlocal\Test;

use SmashPig\PaymentProviders\dlocal\Audit\DlocalAudit;
use SmashPig\Tests\BaseSmashPigUnitTestCase;

/**
 * Verify dlocal audit file processor functions
 *
 * @group Audit
 * @group Dlocal
 */
class AuditTest extends BaseSmashPigUnitTestCase {
	/**
	 * Normal donation
	 */
	public function testProcessDonation() {
		$processor = new DlocalAudit();
		$output = $processor->parseFile( __DIR__ . '/../Data/donation.csv' );
		$this->assertCount( 1, $output, 'Should have found one donation' );
		$actual = $output[0];
		$expected = [
			'gateway' => 'dlocal',
			'date' => 1686911383,
			'gross' => '5.00',
			'contribution_tracking_id' => '266221341',
			'country' => 'BR',
			'currency' => 'BRL',
			'email' => 'donoriffic@example.org',
			'gateway_txn_id' => '5432123',
			'invoice_id' => '266221341.0',
			'payment_method' => 'cc',
			'payment_submethod' => 'mc',
			'settled_date' => 1686916832,
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
		$processor = new DlocalAudit();
		$output = $processor->parseFile( __DIR__ . '/../Data/refund.csv' );
		$this->assertCount( 1, $output, 'Should have found one refund' );
		$actual = $output[0];
		$expected = [
			'gateway' => 'dlocal',
			'contribution_tracking_id' => '314159265',
			'date' => 1687208709,
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
		$processor = new DlocalAudit();
		$output = $processor->parseFile( __DIR__ . '/../Data/chargebacks.csv' );
		$this->assertCount( 1, $output, 'Should have found one chargeback' );
		$actual = $output[0];
		$expected = [
			'gateway' => 'dlocal',
			'contribution_tracking_id' => '314159265',
			'date' => 1687208709,
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
