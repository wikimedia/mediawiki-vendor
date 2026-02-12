<?php
declare( strict_types=1 );

namespace SmashPig\PaymentProviders\PayPal\Test;

require_once 'AuditTestBase.php';

/**
 * Verify PayPal audit file normalization functions
 *
 * @group PayPal
 * @group Audit
 */
class STLAuditTest extends AuditTestBase {

	public function testProcessFile(): void {
		$output = $this->processFile( 'stl.csv' );
		$this->assertCount( 6, $output, 'Should have found one row' );

		$this->assertEquals( [
			'payment_method' => 'paypal',
			'currency' => 'AUD',
			'exchange_rate' => 1,
			'settled_currency' => 'AUD',
			'date' => 1767686430,
			'gateway' => 'paypal',
			'audit_file_gateway' => 'paypal',
			'settled_total_amount' => 52,
			'settled_fee_amount' => -2.25,
			'settled_net_amount' => 49.75,
			'gross' => 52,
			'fee' => 2.25,
			'original_fee_amount' => -2.25,
			'gateway_txn_id' => '5678',
			'settled_date' => strtotime( '2026/01/06 00:00:30 -0800' ),
			'contribution_tracking_id' => '2444',
			'order_id' => '2444.1',
			'settlement_batch_reference' => '20260106',
			'original_total_amount' => '52',
			'original_net_amount' => '49.75',
			'original_currency' => 'AUD',
		], $output[0] );

		$this->assertEquals( [
			'payment_method' => 'paypal',
			'currency' => 'GBP',
			'exchange_rate' => 1,
			'settled_currency' => 'GBP',
			'date' => 1767771099,
			'gateway' => 'paypal_ec',
			'audit_file_gateway' => 'paypal',
			'settled_total_amount' => -1.34,
			'settled_net_amount' => -0.87,
			'settled_fee_amount' => 0.47,
			'original_total_amount' => '-1.34',
			'original_net_amount' => '-0.87',
			'original_currency' => 'GBP',
			'original_fee_amount' => 0.47,
			'gross' => 1.34,
			'fee' => 0.47,
			'gateway_txn_id' => '6899',
			'settled_date' => strtotime( '2026/01/06 23:31:39 -0800' ),
			'contribution_tracking_id' => 2216,
			'order_id' => '2216.15',
			'settlement_batch_reference' => '20260106',
			'type' => 'refund',
			'gateway_refund_id' => '6899',
			'gross_currency' => 'GBP',
			'gateway_parent_id' => '99900',
		], $output[3] );

		$this->assertEquals( [
			'settled_currency' => 'GBP',
			'settled_total_amount' => 8.40,
			'gateway' => 'paypal',
			'type' => 'payout',
			'gateway_txn_id' => '',
			'invoice_id' => '',
			'settlement_batch_reference' => '20260106',
			'settled_date' => 1767686400,
			'date' => 1767686400,
		], $output[4] );

		$this->assertEquals( [
			'settled_currency' => 'AUD',
			'settled_total_amount' => 54.35,
			'gateway' => 'paypal',
			'type' => 'payout',
			'gateway_txn_id' => '',
			'invoice_id' => '',
			'settlement_batch_reference' => '20260106',
			'settled_date' => 1767686400,
			'date' => 1767686400,
		], $output[5] );
	}

	public function testProcessConvertedCurrencyTransaction(): void {
		$output = $this->processFile( 'stl_currency_convert.csv' );
		$this->assertCount( 1, $output, 'Should have found one row' );

		$this->assertEquals( [
			'payment_method' => 'paypal',
			'currency' => 'BRL',
			'original_currency' => 'BRL',
			'settled_currency' => 'USD',
			'exchange_rate' => 0.17771739130434783,
			'date' => strtotime( '2026/01/06 04:26:31 -0800' ),
			'gateway' => 'gravy',
			'audit_file_gateway' => 'paypal',
			'settled_total_amount' => '3.91',
			'settled_fee_amount' => '-0.64',
			'settled_net_amount' => '3.27',
			'gross' => 22,
			'fee' => 3.6,
			'original_total_amount' => 22,
			'original_fee_amount' => -3.6,
			'original_net_amount' => 18.4,
			'gateway_txn_id' => 'bc4ae813-a43e-4dd4-91b8-3576ca2d3804',
			'settled_date' => strtotime( '2026/01/06 04:26:31 -0800' ),
			'contribution_tracking_id' => 7233,
			'order_id' => '7233.6',
			'settlement_batch_reference' => '20260106',
			'backend_processor_txn_id' => '1DV3',
			'backend_processor' => 'paypal',
			'payment_orchestrator_reconciliation_id' => '5jImyEK1vFvvvmoxlWR7SO',

		], $output[0] );
	}

	public function testProcessConvertedCurrencyRefundTransaction(): void {
		$output = $this->processFile( 'stl_brl_refund.csv' );
		$this->assertCount( 1, $output, 'Should have found one row' );

		$this->assertEquals( [
			'payment_method' => 'paypal',
			'currency' => 'BRL',
			'original_currency' => 'BRL',
			'settled_currency' => 'USD',
			'exchange_rate' => 0.18158347676419967,
			'date' => strtotime( '2025/12/23 08:57:17 -0800' ),
			'gateway' => 'paypal_ec',
			'audit_file_gateway' => 'paypal',
			'settled_total_amount' => -2.72,
			'settled_fee_amount' => '0.61',
			'settled_net_amount' => '-2.11',
			'gross' => 15.0,
			'fee' => 3.38,
			// Fee of 3.38 BRL refunded to us, we refund 15 BRL to the donor
			'original_fee_amount' => 3.38,
			'original_net_amount' => '-11.62',
			'original_total_amount' => '-15',
			'gateway_txn_id' => '451689',
			'contribution_tracking_id' => 233490290,
			'order_id' => '233490290.3',
			'settlement_batch_reference' => '20251223',
			'settled_date' => strtotime( '2025/12/23 08:57:17 -0800' ),
			'type' => 'refund',
			'gateway_refund_id' => '451689',
			'gross_currency' => 'BRL',
			'gateway_parent_id' => '3K905',

		], $output[0] );
	}

	public function testProcessConvertedCurrencyPayout(): void {
		$output = $this->processFile( 'stl_payout_currency_convert.csv' );
		$this->assertCount( 3, $output, 'Should have found one row' );

		// This row is the donation - $200.
		$this->assertEquals( 200, $output[0]['settled_total_amount'] );
		$this->assertEquals( 'AUD', $output[0]['currency'] );
		$this->assertSame( '244.1', $output[0]['order_id'] );

		// We have 2 payout rows - we are looking to make sure
		// the payout rows take the currency convert transaction into account
		// it the payout means the balance movement coming from
		// donations and refunds - the 'batch' and it ignores transfers.
		$this->assertSame( 0, $output[1]['settled_total_amount'] );
		$this->assertSame( 'USD', $output[1]['settled_currency'] );
		$this->assertSame( 'payout', $output[1]['type'] );

		$this->assertEquals( 200, $output[2]['settled_total_amount'] );
		$this->assertEquals( 'AUD', $output[2]['settled_currency'] );
		$this->assertEquals( 'payout', $output[2]['type'] );
	}

	public function testProcessChargebackWithFee(): void {
		$output = $this->processFile( 'stl_chargeback_with_fee.csv' );
		$this->assertCount( 1, $output, 'Should have found one row' );
		$this->assertEquals( [
			'payment_method' => 'paypal',
			'currency' => 'USD',
			'exchange_rate' => 1,
			'settled_currency' => 'USD',
			'original_currency' => 'USD',
			'settled_date' => strtotime( '2026/01/14 12:38:07 -0800' ),
			'date' => strtotime( '2026/01/14 12:38:07 -0800' ),
			'gateway' => 'paypal',
			'audit_file_gateway' => 'paypal',
			'settled_total_amount' => -3,
			'settled_fee_amount' => -20,
			'settled_net_amount' => -23,
			'settlement_batch_reference' => '20260114',
			'original_total_amount' => -3,
			'original_net_amount' => -23,
			'original_fee_amount' => -20,
			'gross' => 3,
			'fee' => 20,
			'gateway_txn_id' => '5K823',
			'contribution_tracking_id' => null,
			'order_id' => '',
			'type' => 'chargeback',
			'gateway_refund_id' => '5K823',
			'gross_currency' => 'USD',
			'gateway_parent_id' => '59M83',
		], $output[0] );
	}

	public function testProcessChargebackReversal(): void {
		$output = $this->processFile( 'stl_chargeback_reversal.csv' );
		$this->assertCount( 1, $output, 'Should have found one row' );
		$this->assertEquals( [
			'payment_method' => 'paypal',
			'currency' => 'USD',
			'exchange_rate' => 1,
			'settled_currency' => 'USD',
			'original_currency' => 'USD',
			'settled_date' => strtotime( '2026/01/14 12:38:07 -0800' ),
			'date' => strtotime( '2026/01/14 12:38:07 -0800' ),
			'gateway' => 'paypal',
			'audit_file_gateway' => 'paypal',
			'settled_total_amount' => 26,
			'settled_fee_amount' => 20,
			'settled_net_amount' => 46,
			'settlement_batch_reference' => '20260114',
			'original_total_amount' => 26,
			'original_net_amount' => '46',
			'original_fee_amount' => '20',
			'gateway_txn_id' => '5K823',
			'gateway_parent_id' => '59M83',
			'contribution_tracking_id' => 12345,
			'order_id' => '12345.1',
			'type' => 'chargeback_reversed',
			'gross' => 26.0,
			'fee' => 20.0,
		], $output[0] );
	}
}
