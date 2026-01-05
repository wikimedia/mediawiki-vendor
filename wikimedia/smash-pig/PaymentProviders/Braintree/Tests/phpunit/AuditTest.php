<?php
namespace SmashPig\PaymentProviders\Braintree\Test;

use SmashPig\PaymentProviders\Braintree\Audit\BraintreeAudit;
use SmashPig\Tests\BaseSmashPigUnitTestCase;

/**
 * Verify Braintree audit file processor functions
 *
 * @group Audit
 * @group Braintree
 */
class AuditTest extends BaseSmashPigUnitTestCase {
	/**
	 * Normal donation
	 */
	public function testProcessDonation(): void {
		$processor = new BraintreeAudit();
		$output = $processor->parseFile( __DIR__ . '/../Data/settlement_batch_report_2022-06-27.json' );
		$this->assertCount( 2, $output, 'Should have found two donations' );
		$actualPaypal = $output[0];
		$expectedPaypal = [
			'gateway' => 'braintree',
			'date' => 1656383927,
			'gross' => '3.33',
			'contribution_tracking_id' => '20',
			'currency' => 'USD',
			'email' => 'fr-tech+donor@wikimedia.org',
			'gateway_txn_id' => 'dHJhbnNhY3Rpb25fNDQ3ODQwcmM',
			'invoice_id' => '20.1',
			'phone' => null,
			'first_name' => 'f',
			'last_name' => 'doner',
			'payment_method' => 'paypal',
			'audit_file_gateway' => 'braintree',
		];
		$this->assertEquals( $expectedPaypal, $actualPaypal, 'Did not parse paypal donation correctly' );
		$actualVenmo = $output[1];
		$expectedVenmo = [
			'gateway' => 'braintree',
			'audit_file_gateway' => 'braintree',
			'date' => 1690227624,
			'gross' => '10.00',
			'contribution_tracking_id' => '68',
			'currency' => 'USD',
			'email' => 'iannievan@gmail.com',
			'gateway_txn_id' => 'dHJhbnNhY3Rpb25fZ3p5MnMwbjk',
			'invoice_id' => '68.1',
			'phone' => null,
			'first_name' => 'Ann',
			'last_name' => 'Fan',
			'payment_method' => 'venmo',
		];
		$this->assertEquals( $expectedVenmo, $actualVenmo, 'Did not parse venmo donation correctly' );
	}

	public function testProcessRawDonation(): void {
		$processor = new BraintreeAudit();
		$output = $processor->parseFile( __DIR__ . '/../Data/raw_settlement_batch_report.json' );
		$this->assertCount( 3, $output, 'Should have found two donations' );
		$expected = [
			'gateway' => 'braintree',
			'audit_file_gateway' => 'braintree',
			'date' => strtotime( '2025-12-21T22:58:37.000000Z' ),
			'gross' => '3.10',
			'original_total_amount' => '3.10',
			'settled_net_amount' => '3.10',
			'settled_total_amount' => '3.10',
			'settled_fee_amount' => '0',
			'contribution_tracking_id' => '24315',
			'original_currency' => 'USD',
			'settled_currency' => 'USD',
			'exchange_rate' => 1,
			'currency' => 'USD',
			'settlement_batch_reference' => '20251222',
			'settled_date' => strtotime( '2025-12-22 UTC' ),
			'invoice_id' => '24315.1',
			'phone' => null,
			'email' => null,
			'first_name' => null,
			'last_name' => null,
			'payment_method' => 'venmo',
			'external_identifier' => 'xyz',
			'gateway_txn_id' => 'abcde',
		];
		$this->assertEquals( $expected, $output[0], 'Did not parse paypal donation correctly' );
	}

	/**
	 * Normal donation
	 */
	public function testProcessOrchestratorDonation(): void {
		$processor = new BraintreeAudit();
		$output = $processor->parseFile( __DIR__ . '/../Data/settlement_batch_report_gravy_2022-06-27.json' );
		$this->assertCount( 1, $output, 'Should have found two donations' );
		$actual = $output[0];
		$expected = [
			'gateway' => 'gravy',
			'audit_file_gateway' => 'braintree',
			'backend_processor' => 'braintree',
			'backend_processor_txn_id' => 'dHJhbnNhY3Rpb25fNDQ3ODQwcmM',
			'payment_orchestrator_reconciliation_id' => '4dKvU4tsIv5DZRxK4jYbib',
			'date' => 1656383927,
			'gross' => '3.33',
			'currency' => 'USD',
			'email' => 'fr-tech+donor@wikimedia.org',
			'gateway_txn_id' => 'dHJhbnNhY3Rpb25fNDQ3ODQwcmM',
			'invoice_id' => '4dKvU4tsIv5DZRxK4jYbib',
			'phone' => null,
			'first_name' => 'f',
			'last_name' => 'donor',
			'payment_method' => 'venmo',
		];
		$this->assertEquals( $expected, $actual, 'Did not parse paypal donation correctly' );
	}

	/**
	 * Now try a refund
	 */
	public function testProcessRefund(): void {
		$processor = new BraintreeAudit();
		$output = $processor->parseFile( __DIR__ . '/../Data/settlement_batch_report_refund_2022-06-27.json' );
		$this->assertCount( 2, $output, 'Should have found two refund donations' );
		$actualPaypal = $output[0];
		$expectedPaypal = [
			'gateway' => 'braintree',
			'audit_file_gateway' => 'braintree',
			'date' => 1656390820,
			'gross' => '10.00',
			'contribution_tracking_id' => '34',
			'currency' => 'USD',
			'email' => 'iannievan@gmail.com',
			'gateway_parent_id' => 'dHJhbnNhY3Rpb25fMTYxZXdrMjk',
			'gateway_refund_id' => 'cmVmdW5kXzR6MXlyZ3o1',
			'invoice_id' => '34.1',
			'phone' => null,
			'first_name' => 'wenjun',
			'last_name' => 'fan',
			'payment_method' => 'paypal',
			'type' => 'refund',
		];
		$this->assertEquals( $expectedPaypal, $actualPaypal, 'Did not parse paypal refund correctly' );
		$actualVenmo = $output[1];
		$expectedVenmo = [
			'gateway' => 'braintree',
			'audit_file_gateway' => 'braintree',
			'date' => 1690485762,
			'gross' => '5.00',
			'contribution_tracking_id' => '61',
			'currency' => 'USD',
			'email' => 'iannievan@gmail.com',
			'gateway_parent_id' => 'dHJhbnNhY3Rpb25fY2EyMWdnNjk',
			'gateway_refund_id' => 'cmVmdW5kX2V5NWdnNjJl',
			'invoice_id' => '61.1',
			'phone' => null,
			'first_name' => 'Ann',
			'last_name' => 'Fan',
			'payment_method' => 'venmo',
			'type' => 'refund',
		];
		$this->assertEquals( $expectedVenmo, $actualVenmo, 'Did not parse venmo refund correctly' );
	}

	/**
	 * Process raw refund
	 */
	public function testProcessRawRefund(): void {
		$processor = new BraintreeAudit();
		$output = $processor->parseFile( __DIR__ . '/../Data/raw_batch_report_refund.json' );
		$this->assertCount( 2, $output, 'Should have found two refunds' );
		$expected = [
			'gateway' => 'braintree',
			'audit_file_gateway' => 'braintree',
			'date' => strtotime( '2025-12-19T19:26:35.000000Z UTC' ),
			'gross' => '52.00',
			'original_total_amount' => -52.0,
			'settled_net_amount' => -52.0,
			'settled_total_amount' => -52.0,
			'contribution_tracking_id' => '2402',
			'currency' => 'USD',
			'email' => null,
			'gateway_parent_id' => 'dHJh',
			'gateway_refund_id' => 'cmVmd',
			'invoice_id' => '2402.2',
			'phone' => null,
			'first_name' => null,
			'last_name' => null,
			'payment_method' => 'venmo',
			'type' => 'refund',
			'original_currency' => 'USD',
			'external_identifier' => 'J',
			'settled_date' => strtotime( '2025-12-22 UTC' ),
			'settlement_batch_reference' => '20251222',
			'settled_fee_amount' => 0,
			'exchange_rate' => '1',
			'settled_currency' => 'USD',
		];
		$this->assertEquals( $expected, $output[0], 'Did not parse refund correctly' );
	}

	/**
	 * And a dispute
	 */
	public function testProcessDispute(): void {
		$processor = new BraintreeAudit();
		$output = $processor->parseFile( __DIR__ . '/../Data/settlement_batch_report_dispute_2022-06-27.json' );
		$this->assertCount( 2, $output, 'Should have found two dispute donations' );
		$actualPaypal = $output[0];
		$expectedPaypal = [
				'gateway' => 'braintree',
				'audit_file_gateway' => 'braintree',
				'date' => 1656381367,
				'gross' => '3.33',
				'contribution_tracking_id' => '17',
				'currency' => 'USD',
				'email' => 'fr-tech+donor@wikimedia.org',
				'gateway_txn_id' => 'dHJhbnNhY3Rpb25fa2F4eG1ycjE',
				'invoice_id' => '17.1',
				'phone' => null,
				'first_name' => 'f',
				'last_name' => 'doner',
				'payment_method' => 'paypal',
				'type' => 'chargeback',
			];
		$this->assertEquals( $expectedPaypal, $actualPaypal, 'Did not parse dispute paypal correctly' );
		$actualVenmo = $output[1];
		$expectedVenmo = [
				'gateway' => 'braintree',
				'audit_file_gateway' => 'braintree',
				'date' => 1690485762,
				'gross' => '5.00',
				'contribution_tracking_id' => '61',
				'currency' => 'USD',
				'email' => 'iannievan@gmail.com',
				'gateway_txn_id' => 'dHJhbnNhY3Rpb25fY2EyMWdnNjk',
				'invoice_id' => '61.1',
				'phone' => null,
				'first_name' => 'Ann',
				'last_name' => 'Fan',
				'payment_method' => 'venmo',
				'type' => 'chargeback',
			];
		$this->assertEquals( $expectedVenmo, $actualVenmo, 'Did not parse dispute venmo correctly' );
	}

	/**
	 * And a dispute
	 */
	public function testProcessRawDispute(): void {
		$processor = new BraintreeAudit();
		$output = $processor->parseFile( __DIR__ . '/../Data/raw_batch_report_dispute.json' );
		$this->assertCount( 2, $output, 'Should have found two disputes that are resolved, others ignored' );
		$actualPaypal = $output[0];
		$expectedPaypal = [
			'gateway' => 'braintree',
			'audit_file_gateway' => 'braintree',
			'date' => strtotime( '2025-12-21 UTC' ),
			'gross' => '5.35',
			'original_total_amount' => '-5.35',
			'settled_net_amount' => '-5.35',
			'settled_total_amount' => '-5.35',
			'contribution_tracking_id' => '2387',
			'currency' => 'USD',
			'email' => null,
			'gateway_refund_id' => 'ZGlzcH',
			'invoice_id' => '2387.3',
			'phone' => null,
			'first_name' => null,
			'last_name' => null,
			'payment_method' => 'venmo',
			'type' => 'chargeback',
			'gateway_parent_id' => 'dHJhb',
			'original_currency' => 'USD',
			'external_identifier' => 'D',
			'settled_date' => strtotime( '2025-12-21 UTC' ),
			'settlement_batch_reference' => '20251221',
			'settled_fee_amount' => 0,
			'exchange_rate' => 1,
			'settled_currency' => 'USD',
		];
		$this->assertEquals( $expectedPaypal, $actualPaypal, 'Did not parse dispute correctly' );
	}
}
