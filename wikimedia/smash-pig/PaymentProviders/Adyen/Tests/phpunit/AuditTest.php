<?php
namespace SmashPig\PaymentProviders\Adyen\Test;

use SmashPig\PaymentProviders\Adyen\Audit\AdyenSettlementDetailReport;
use SmashPig\Tests\BaseSmashPigUnitTestCase;

/**
 * Verify Adyen audit file processor functions
 *
 * @group Adyen
 * @group Audit
 */
class AuditTest extends BaseSmashPigUnitTestCase {
	/**
	 * Normal donation
	 */
	public function testProcessSettlementDetailDonation() {
		$processor = new AdyenSettlementDetailReport();
		$output = $processor->parseFile( __DIR__ . '/../Data/settlement_detail_report_donation.csv' );
		$this->assertSame( 1, count( $output ), 'Should have found one donation' );
		$actual = $output[0];
		$expected = [
			'gateway' => 'adyen',
			'gateway_account' => 'WikimediaCOM',
			'gross' => '1.00',
			'contribution_tracking_id' => '33992337',
			'currency' => 'USD',
			'gateway_txn_id' => '5364893193133131',
			'invoice_id' => '33992337.0',
			'modification_reference' => '5364893193133131',
			'payment_method' => 'cc',
			'payment_submethod' => 'visa-debit',
			'date' => 1455840651,
			'settled_currency' => 'USD',
			'fee' => '0.24',
			'settled_gross' => '0.76',
			'settled_fee' => '0.24',
		];
		$this->assertEquals( $expected, $actual, 'Did not parse donation correctly' );
	}

	/**
	 * iDEAL donation with variant that we should discard
	 */
	public function testProcessDonationIdeal() {
		$processor = new AdyenSettlementDetailReport();
		$output = $processor->parseFile( __DIR__ . '/../Data/settlement_detail_report_donation-ideal.csv' );
		$this->assertSame( 1, count( $output ), 'Should have found one donation' );
		$actual = $output[0];
		$expected = [
			'gateway' => 'adyen',
			'gateway_account' => 'WikimediaCOM',
			'gross' => '5.35',
			'contribution_tracking_id' => '80188432',
			'currency' => 'EUR',
			'gateway_txn_id' => '1515876691993221',
			'modification_reference' => '1515876691993221',
			'invoice_id' => '80188432.1',
			'payment_method' => 'rtbt',
			'payment_submethod' => 'rtbt_ideal',
			'date' => 1582488844,
			'settled_currency' => 'USD',
			'fee' => '0.25',
			'settled_gross' => '5.43',
			'settled_fee' => '0.27',
		];
		$this->assertEquals( $expected, $actual, 'Did not parse donation correctly' );
	}

	/**
	 * Now try a refund
	 */
	public function testProcessSettlementDetailRefund() {
		$processor = new AdyenSettlementDetailReport();
		$output = $processor->parseFile( __DIR__ . '/../Data/settlement_detail_report_refund.csv' );
		$this->assertSame( 1, count( $output ), 'Should have found one refund' );
		$actual = $output[0];
		$expected = [
			'gateway' => 'adyen',
			'gateway_account' => 'WikimediaCOM',
			'contribution_tracking_id' => '92598312',
			'date' => 1455128736,
			'gross' => '1.00',
			'gateway_parent_id' => '4522268860022701',
			'gateway_refund_id' => '4522268869855336',
			'gross_currency' => 'USD',
			'invoice_id' => '92598312.0',
			'type' => 'refund',
			'gateway_txn_id' => '4522268860022701',
			'payment_method' => 'cc',
			'payment_submethod' => 'visa',
		];
		$this->assertEquals( $expected, $actual, 'Did not parse refund correctly' );
	}

	/**
	 * And a chargeback
	 */
	public function testProcessSettlementDetailChargeback() {
		$processor = new AdyenSettlementDetailReport();
		$output = $processor->parseFile( __DIR__ . '/../Data/settlement_detail_report_chargeback.csv' );
		$this->assertSame( 1, count( $output ), 'Should have found one chargeback' );
		$actual = $output[0];
		$expected = [
			'gateway' => 'adyen',
			'gateway_account' => 'WikimediaCOM',
			'contribution_tracking_id' => '92598318',
			'date' => 1455128736,
			'gross' => '1.00',
			'gateway_parent_id' => '4555568860022701',
			'gateway_refund_id' => '4555568869855336',
			'gross_currency' => 'USD',
			'invoice_id' => '92598318.0',
			'type' => 'chargeback',
			'gateway_txn_id' => '4555568860022701',
			'payment_method' => 'cc',
			'payment_submethod' => 'visa',
		];
		$this->assertEquals( $expected, $actual, 'Did not parse chargeback correctly' );
	}
}
