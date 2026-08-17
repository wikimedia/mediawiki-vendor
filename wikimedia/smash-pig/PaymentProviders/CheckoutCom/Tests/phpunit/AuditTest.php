<?php

namespace SmashPig\PaymentProviders\CheckoutCom\Tests;

use SmashPig\PaymentProviders\CheckoutCom\Audit\CheckoutComAudit;
use SmashPig\Tests\BaseSmashPigUnitTestCase;

class AuditTest extends BaseSmashPigUnitTestCase {

	public function testProcessSettlementBreakdownDonation(): void {
		$actual = $this->processFile( '922d7c4b-9181-4fe9-b5e8-cb3f8c0883c1' );

		$expected = [
			'gateway' => 'gravy',
			'audit_file_gateway' => 'checkoutcom',
			'backend_processor' => 'checkoutcom',
			'gateway_account' => 'Example donation channel',
			'gateway_txn_id' => '922d7c4b-9181-4fe9-b5e8-cb3f8c0883c1',
			'backend_processor_txn_id' => 'pay_test_charge_001',
			'auth_id' => 'pay_test_charge_001',
			'payment_orchestrator_reconciliation_id' => '4RpfKjZxqXWKsUOeHaVteD',
			'settlement_batch_reference' => '00000003K599',
			'payment_method' => 'cc',
			'payment_submethod' => 'visa',
			'date' => 1782900000,
			'settled_date' => 1782950400,
			'exchange_rate' => 1.0,
			'type' => 'donation',
			'gross' => '50.00',
			'currency' => 'USD',
			'original_currency' => 'USD',
			'settled_currency' => 'USD',
			'original_total_amount' => '50.00',
			'original_fee_amount' => '-1.60',
			'original_net_amount' => '48.40',
			'settled_fee_amount' => '-1.60',
			'settled_net_amount' => '48.40',
			'settled_total_amount' => '50.00',
		];
		$this->assertEquals( $expected, $actual );
	}

	public function testProcessSettlementBreakdownFee(): void {
		$output = $this->processFile( 'fee-pay_test_fee_0011782910800Charge' );

		$this->assertEquals( [
			'gateway' => 'checkoutcom',
			'audit_file_gateway' => 'checkoutcom',
			'type' => 'fee',
			'gateway_txn_id' => 'fee-pay_test_fee_0011782910800Charge',
			'gateway_account' => 'Example donation channel',
			'settlement_batch_reference' => '00000003K599',
			'date' => 1782910800,
			'settled_date' => 1782950400,
			'settled_currency' => 'USD',
			'settled_total_amount' => '0.00',
			'settled_fee_amount' => '-0.25',
			'settled_net_amount' => '-0.25',
		], $output );
	}

	public function testProcessSettlementFeeRowSquashed(): void {
		$actual = $this->processFile();
		// 9 rows in file + rounding + payout less squashed fee row.
		$this->assertCount( 10, $actual );
		foreach ( $actual as $row ) {
			if ( $row['type'] === 'fee' ) {
				$this->assertStringNotContainsString( 'pay_test_token_001', $row['gateway_txn_id'], 'row should have been squashed' );
			}
		}
	}

	public function testProcessSettlementRoundingRow(): void {
		$feeRow = $this->processFile( 'rounding-00000003K599' );
		$this->assertSame( 'fee', $feeRow['type'] );
		$this->assertSame( '0.01', $feeRow['settled_fee_amount'] );
		$this->assertSame( '0.01', $feeRow['settled_net_amount'] );
		$this->assertSame( '00000003K599', $feeRow['settlement_batch_reference'] );
		$this->assertSame( 'rounding-00000003K599', $feeRow['gateway_txn_id'] );
	}

	public function testProcessSettlementBreakdownPayout(): void {
		$payout = $this->processFile( '00000003K599' );
		$this->assertSame( 'payout', $payout['type'] );
		$this->assertSame( '79.51', $payout['settled_total_amount'] );
		$this->assertSame( '00000003K599', $payout['settlement_batch_reference'] );
		$this->assertSame( '00000003K599', $payout['gateway_txn_id'] );
	}

	/**
	 * @param string|null $txn_id
	 * @param string $fileName
	 *
	 * @return array
	 */
	public function processFile( ?string $txn_id = null, string $fileName = 'settlement-breakdown_ent_testcheckoutfixture_20260702_00000003k599_1.csv' ): array {
		$processor = new CheckoutComAudit();
		$rows = $processor->parseFile( __DIR__ . '/../Data/' . $fileName );
		if ( $txn_id !== null ) {
			foreach ( $rows as $row ) {
				if ( $row['gateway_txn_id'] === $txn_id ) {
					return $row;
				}
			}
		}
		return $rows;
	}

}
