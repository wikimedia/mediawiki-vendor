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
			'audit_file_gateway' => 'dlocal',
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
			'settled_fee_amount' => '-0.03',
			'settled_net_amount' => 1.47,
			'settled_total_amount' => 1.50,
			'original_total_amount' => '5.00',
			'exchange_rate' => '.3',
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
			'audit_file_gateway' => 'dlocal',
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
			'audit_file_gateway' => 'dlocal',
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

	/**
	 * Normal donation
	 */
	public function testProcessSettlementReport() {
		$processor = new DlocalAudit();
		$output = $processor->parseFile( __DIR__ . '/../Data/Wikimedia_Settlement_Reports_20260110.csv' );
		$this->assertCount( 7, $output, 'Should have found five donations' );

		$this->assertEquals( [
			'gateway' => 'dlocal',
			'audit_file_gateway' => 'dlocal',
			'date' => strtotime( '2026-01-09 UTC' ),
			'settled_currency' => 'USD',
			'gateway_txn_id' => '805113614',
			'invoice_id' => '805113614',
			'settled_date' => strtotime( '2026-01-09 UTC' ),
			'settled_fee_amount' => '-2.38',
			'settled_net_amount' => '-2.38',
			'settled_total_amount' => '0.0',
			'settlement_batch_reference' => '20260109',
			'type' => 'fee',
		], $output[0], 'Did not parse adjustment correctly' );

		$this->assertEquals( [
			'gateway_txn_id' => '34bb2708-c228-4559-8405-17342b98cf74',
			'gateway' => 'gravy',
			'audit_file_gateway' => 'dlocal',
			'date' => 1766190909,
			'settled_date' => 1766190910,
			'settlement_batch_reference' => '20260109',
			'original_total_amount' => '170.00',
			'original_fee_amount' => '-0.37',
			'original_net_amount' => '169.63',
			'settled_total_amount' => '254.01',
			'settled_fee_amount' => '-0.06',
			'settled_net_amount' => '253.95',
			'gross' => '170.00',
			'fee' => '0.37',
			'exchange_rate' => 0.154790,
			'settled_currency' => 'USD',
			'currency' => 'BRL',
			'original_currency' => 'BRL',
			'order_id' => '',
			'email' => 'a@example.org',
			'contribution_tracking_id' => '',
			'backend_processor_txn_id' => 'T-648-x1438o',
			'backend_processor' => 'dlocal',
			'payment_orchestrator_reconciliation_id' => '1bV83QePtLcLAxiIgM4et2',
		], $output[1] );

		$this->assertEquals( [
			'gateway_txn_id' => 'T-648-x17i8',
			'gateway' => 'dlocal',
			'audit_file_gateway' => 'dlocal',
			'date' => 1766379133,
			'settled_date' => 1766379134,
			'settlement_batch_reference' => '20260109',
			'settled_total_amount' => '3.77',
			'settled_fee_amount' => '-0.07',
			'settled_net_amount' => '3.70',
			'original_total_amount' => '70.00',
			'original_net_amount' => '68.60',
			'original_fee_amount' => '-1.40',
			'gross' => '70.00',
			'fee' => '1.40',
			'exchange_rate' => 0.053887,
			'settled_currency' => 'USD',
			'currency' => 'MXN',
			'original_currency' => 'MXN',
			'order_id' => '204515653.22',
			'email' => 'd@example.org',
			'contribution_tracking_id' => '204515653',
		], $output[4] );

		$this->assertEquals( [
			'gateway' => 'dlocal',
			'audit_file_gateway' => 'dlocal',
			'date' => strtotime( '2026-01-09 UTC' ),
			'settled_currency' => 'USD',
			'gateway_txn_id' => '642926',
			'invoice_id' => '642926',
			'settled_date' => strtotime( '2026-01-09 UTC' ),
			'settled_fee_amount' => '-80.00',
			'settled_net_amount' => '-80.00',
			'settled_total_amount' => '0.0',
			'settlement_batch_reference' => '20260109',
			'type' => 'fee',
		], $output[5], 'Did not parse fee correctly' );

		$this->assertEquals( [
			'gateway' => 'dlocal',
			'audit_file_gateway' => 'dlocal',
			'date' => strtotime( '2026-01-09 UTC' ),
			'settled_currency' => 'USD',
			'gateway_txn_id' => '642926-rounding',
			'invoice_id' => '642926-rounding',
			'settled_date' => strtotime( '2026-01-09 UTC' ),
			'settled_fee_amount' => '0.01',
			'settled_net_amount' => '0.01',
			'settled_total_amount' => '0.0',
			'settlement_batch_reference' => '20260109',
			'type' => 'fee',
		], $output[6], 'Did not parse rounding adjustment correctly' );
	}
}
