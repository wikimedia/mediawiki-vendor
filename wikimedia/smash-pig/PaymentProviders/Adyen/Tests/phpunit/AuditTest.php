<?php
namespace SmashPig\PaymentProviders\Adyen\Test;

use SmashPig\PaymentProviders\Adyen\Audit\AdyenPaymentsAccountingReport;
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
		$this->assertCount( 2, $output, 'Should have found one donation and one payout row' );
		$actual = $output[0];
		$expected = [
			'gateway' => 'adyen',
			'audit_file_gateway' => 'adyen',
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
			'fee' => '0.24',
			'settled_gross' => '0.76',
			'settlement_batch_reference' => '2',
			'exchange_rate' => '1',
			'settled_date' => null,
			'settled_currency' => 'USD',
			'original_currency' => 'USD',
			'original_total_amount' => 1.0,
			'original_fee_amount' => -0.24,
			'original_net_amount' => 0.76,
			'settled_fee_amount' => -0.24,
			'settled_net_amount' => 0.76,
			'settled_total_amount' => 1.0,
		];
		$this->assertEquals( $expected, $actual, 'Did not parse donation correctly' );
	}

	/**
	 * Normal donation, but with the PSP Reference moved to the last column
	 */
	public function testProcessSettlementDetailDonationReordered() {
		$processor = new AdyenSettlementDetailReport();
		$output = $processor->parseFile( __DIR__ . '/../Data/settlement_detail_report_donation_reordered.csv' );
		$this->assertCount( 2, $output, 'Should have found one donation row and one payout row' );
		$actual = $output[0];
		$expected = [
			'gateway' => 'adyen',
			'audit_file_gateway' => 'adyen',
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
			'settlement_batch_reference' => '2',
			'exchange_rate' => '1',
			'settled_date' => null,
			'settled_currency' => 'USD',
			'original_currency' => 'USD',
			'original_total_amount' => 1.0,
			'original_fee_amount' => -0.24,
			'original_net_amount' => 0.76,
			'settled_fee_amount' => -0.24,
			'settled_net_amount' => 0.76,
			'settled_total_amount' => 1.0,
		];
		$this->assertEquals( $expected, $actual, 'Did not parse donation correctly' );
	}

	/**
	 * iDEAL donation with variant that we should discard
	 */
	public function testProcessDonationIdeal() {
		$processor = new AdyenSettlementDetailReport();
		$output = $processor->parseFile( __DIR__ . '/../Data/settlement_detail_report_donation-ideal.csv' );
		$this->assertCount( 2, $output, 'Should have found one donation and one payout row' );
		$actual = $output[0];
		$expected = [
			'gateway' => 'adyen',
			'audit_file_gateway' => 'adyen',
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
			'fee' => 0.25,
			'settled_gross' => '5.43',
			'settlement_batch_reference' => '630',
			'exchange_rate' => 1.0656568,
			'settled_date' => null,
			'settled_currency' => 'USD',
			'original_currency' => 'EUR',
			'original_total_amount' => 5.35,
			'original_fee_amount' => -0.25,
			'original_net_amount' => 5.1,
			'settled_fee_amount' => -0.27,
			'settled_net_amount' => 5.43,
			'settled_total_amount' => 5.7,
		];
		$this->assertEquals( $expected, $actual, 'Did not parse donation correctly' );
	}

	/**
	 * ACH donation
	 */
	public function testProcessDonationAch() {
		$processor = new AdyenSettlementDetailReport();
		$output = $processor->parseFile( __DIR__ . '/../Data/settlement_detail_report_donation-ach.csv' );
		$this->assertCount( 2, $output, 'Should have found one donation row and one payout row' );
		$actual = $output[0];
		$expected = [
			'gateway' => 'adyen',
			'audit_file_gateway' => 'adyen',
			'gateway_account' => 'WikimediaCOM',
			'gross' => '1.00',
			'contribution_tracking_id' => '206543313',
			'currency' => 'USD',
			'gateway_txn_id' => 'GDC9ZZ4L2MONEY42',
			'modification_reference' => 'X99BANANA6S8TN25',
			'invoice_id' => '206543313.1',
			'payment_method' => 'dd',
			'payment_submethod' => 'ach',
			'date' => 1717525240,
			'settled_currency' => 'USD',
			'fee' => '0.22',
			'settled_gross' => '0.78',
			'settlement_batch_reference' => 1061,
			'exchange_rate' => 1,
			'settled_date' => null,
			'settled_currency' => 'USD',
			'original_currency' => 'USD',
			'original_total_amount' => 1,
			'original_fee_amount' => -0.22,
			'original_net_amount' => 0.78,
			'settled_fee_amount' => -0.22,
			'settled_net_amount' => 0.78,
			'settled_total_amount' => 1,
		];
		$this->assertEquals( $expected, $actual, 'Did not parse donation correctly' );
	}

	/**
	 * Now try a refund
	 */
	public function testProcessSettlementDetailRefund() {
		$processor = new AdyenSettlementDetailReport();
		$output = $processor->parseFile( __DIR__ . '/../Data/settlement_detail_report_refund.csv' );
		$this->assertCount( 3, $output, 'Should have found one refund and one fee row and one payout row' );
		$actual = $output[0];
		$expected = [
			'gateway' => 'adyen',
			'audit_file_gateway' => 'adyen',
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
			'settlement_batch_reference' => 3,
			'exchange_rate' => 1,
			'fee' => 0,
			'settled_date' => null,
			'settled_currency' => 'USD',
			'original_currency' => 'USD',
			'original_total_amount' => -1.0,
			'original_fee_amount' => 0,
			'original_net_amount' => -1.0,
			'settled_fee_amount' => 0,
			'settled_net_amount' => -1.0,
			'settled_total_amount' => -1.0,
		];
		$this->assertEquals( $expected, $actual, 'Did not parse refund correctly' );
	}

	/**
	 * And a chargeback
	 */
	public function testProcessSettlementDetailChargeback() {
		$processor = new AdyenSettlementDetailReport();
		$output = $processor->parseFile( __DIR__ . '/../Data/settlement_detail_report_chargeback.csv' );
		$this->assertCount( 3, $output, 'Should have found one chargeback and one fee row and one payout row' );
		$actual = $output[0];
		$expected = [
			'gateway' => 'adyen',
			'audit_file_gateway' => 'adyen',
			'gateway_account' => 'WikimediaCOM',
			'contribution_tracking_id' => 92598318,
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
			'settlement_batch_reference' => '3',
			'exchange_rate' => 1,
			'fee' => '-2.00',
			'settled_date' => null,
			'settled_currency' => 'USD',
			'original_currency' => 'USD',
			'original_fee_amount' => '-2.00',
			'original_net_amount' => '-3.00',
			'original_total_amount' => '-1.00',
			'settled_fee_amount' => '-2.00',
			'settled_net_amount' => '-3.00',
			'settled_total_amount' => '-1.00',
		];
		$this->assertEquals( $expected, $actual, 'Did not parse chargeback correctly' );
	}

	/**
	 * And a chargeback
	 */
	public function testProcessSettlementDetailChargebackReversal() {
		$processor = new AdyenSettlementDetailReport();
		$output = $processor->parseFile( __DIR__ . '/../Data/settlement_detail_report_chargeback_reversed.csv' );
		$actual = $output[0];
		$expected = [
			'gateway' => 'adyen',
			'audit_file_gateway' => 'adyen',
			'gateway_account' => 'WikimediaCOM',
			'contribution_tracking_id' => 92598312,
			'date' => 1455128736,
			'gross' => '52',
			'invoice_id' => '92598312.0',
			'type' => 'chargeback_reversed',
			'gateway_txn_id' => '4522268860022701',
			'payment_method' => 'cc',
			'payment_submethod' => 'visa',
			'settlement_batch_reference' => '3',
			'exchange_rate' => 1,
			'fee' => 0.11,
			'settled_date' => null,
			'settled_currency' => 'USD',
			'original_currency' => 'USD',
			'currency' => 'USD',
			'original_fee_amount' => -0.11,
			'original_net_amount' => 51.89,
			'settled_gross' => '51.89',
			'original_total_amount' => 52,
			'settled_fee_amount' => -0.11,
			'settled_net_amount' => 51.89,
			'settled_total_amount' => 52,
			'modification_reference' => '4522268869855336',
		];
		$this->assertEquals( $expected, $actual, 'Did not parse chargeback correctly' );
	}

	public function testProcessPaymentsAccountingNyce() {
		$processor = new AdyenPaymentsAccountingReport();
		$output = $processor->parseFile( __DIR__ . '/../Data/payments_accounting_report_nyce.csv' );
		$this->assertCount( 1, $output );
		$actual = $output[0];
		$expected = [
			'gateway' => 'adyen',
			'audit_file_gateway' => 'adyen',
			'gateway_account' => 'WikimediaDonations',
			'gross' => '10.40',
			'contribution_tracking_id' => '191638898',
			'currency' => 'USD',
			'gateway_txn_id' => 'DASD76ASD7ASD4AS',
			'modification_reference' => 'ASDF5ASDF4QWER3A',
			'invoice_id' => '191638898.1',
			'payment_method' => 'google',
			'payment_submethod' => 'mc',
			'date' => 1694092254,
			'settled_currency' => 'USD',
			'fee' => '0.38',
			'settled_gross' => '10.02',
			'settlement_batch_reference' => null,
			'exchange_rate' => 1,
			'original_currency' => 'USD',
			'original_total_amount' => '10.40',
			'original_net_amount' => '10.02',
			'original_fee_amount' => 0.38,
			'settled_total_amount' => 10.40,
			'settled_net_amount' => '10.02',
			'settled_fee_amount' => 0.38,
			'settled_date' => 1694092254,
		];
		$this->assertEquals( $expected, $actual, 'Did not parse donation correctly' );
	}

	public function testProcessPaymentsAccountingChargeback() {
		$processor = new AdyenPaymentsAccountingReport();
		$output = $processor->parseFile( __DIR__ . '/../Data/payments_accounting_report_chargeback.csv' );
		$this->assertCount( 1, $output );
		$actual = $output[0];
		$expected = [
			'gateway' => 'adyen',
			'audit_file_gateway' => 'adyen',
			'gateway_account' => 'WikimediaDonations',
			// We are looking at a 'Main Amount' (net_amount) of 23.87
			// this is what was deducted from 'us'
			// less $10.65 + .21 fees (10.86) fee_amount
			// = 13.01 total_amount - / gross - this is what the donor received back
			'gross' => 13.01,
			'settled_net_amount' => -23.87,
			'settled_fee_amount' => -10.86,
			'settled_total_amount' => -13.01,
			'original_net_amount' => -23.87,
			'original_total_amount' => -13.01,
			'original_fee_amount' => -10.86,
			'contribution_tracking_id' => '189748459',
			'settled_currency' => 'USD',
			'exchange_rate' => 1,
			'original_currency' => 'USD',
			'gross_currency' => 'USD',
			'gateway_refund_id' => 'ASDF5ASDF4QWER3A',
			'gateway_parent_id' => 'DASD76ASD7ASD4AS',
			'invoice_id' => '189748459.1',
			'payment_method' => 'cc',
			'payment_submethod' => 'mc',
			'date' => 1697133875,
			'type' => 'chargeback',
			'gateway_txn_id' => 'DASD76ASD7ASD4AS',
			'settlement_batch_reference' => null,
			'settled_date' => 1697133875,
		];
		$this->assertEquals( $expected, $actual, 'Did not parse donation correctly' );
	}

	public function testProcessPaymentsAccountingGravyChargeback() {
		$processor = new AdyenPaymentsAccountingReport();
		$output = $processor->parseFile( __DIR__ . '/../Data/payments_accounting_report_gravy_chargeback.csv' );
		$this->assertCount( 1, $output );
		$actual = $output[0];
		$expected = [
			'gateway' => 'gravy',
			'audit_file_gateway' => 'adyen',
			'gateway_account' => 'WikimediaDonations',
			// We refunded 1.75 and paid a fee of $7.99 making our net 9.74
			'gross' => 1.75,
			'original_net_amount' => -9.74,
			'settled_fee_amount' => -7.99,
			'settled_total_amount' => -1.75,
			'settled_net_amount' => -9.74,
			'settled_currency' => 'USD',
			'original_total_amount' => -1.75,
			'original_fee_amount' => -7.99,
			'contribution_tracking_id' => '900000',
			'original_currency' => 'USD',
			'gross_currency' => 'USD',
			'invoice_id' => '900000.1',
			'payment_method' => 'cc',
			'payment_submethod' => 'mc',
			'date' => 1697133875,
			'type' => 'chargeback',
			'gateway_txn_id' => '3f9c958c-ee57-4121-a79e-408946b27077',
			'settlement_batch_reference' => '1131',
			'email' => 'mail@example.com',
			'contribution_tracking_id' => '900000',
			'exchange_rate' => '1',
			'backend_processor_txn_id' => 'DASD76ASD7ASD4AS',
			'backend_processor' => 'adyen',
			'payment_orchestrator_reconciliation_id' => '1w24hGOdCSFLtsgBQr2jKh',
			'backend_processor_parent_id' => 'DASD76ASD7ASD4AS',
			'backend_processor_refund_id' => 'ASDF5ASDF4QWER3A',
			'settled_date' => 1697133875,
		];
		$this->assertEquals( $expected, $actual, 'Did not parse donation correctly' );
	}

	public function testPresentMerchantReference() {
		$processor = new AdyenSettlementDetailReport();
		$output = $processor->parseFile( __DIR__ . '/../Data/settlement_detail_report_ignore.csv' );
		$this->assertCount( 2, $output );
		$this->assertEquals( 'adyen', $output[0]['audit_file_gateway'] );
		$this->assertEquals( 'gravy', $output[0]['gateway'] );
	}
}
