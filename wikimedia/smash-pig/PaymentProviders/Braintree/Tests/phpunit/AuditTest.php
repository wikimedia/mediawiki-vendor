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
	public function testProcessDonation() {
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
		];
		$this->assertEquals( $expectedPaypal, $actualPaypal, 'Did not parse paypal donation correctly' );
		$actualVenmo = $output[1];
		$expectedVenmo = [
			'gateway' => 'braintree',
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

	/**
	 * Now try a refund
	 */
	public function testProcessRefund() {
		$processor = new BraintreeAudit();
		$output = $processor->parseFile( __DIR__ . '/../Data/settlement_batch_report_refund_2022-06-27.json' );
		$this->assertCount( 2, $output, 'Should have found two refund donations' );
		$actualPaypal = $output[0];
		$expectedPaypal = [
			'gateway' => 'braintree',
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
	 * And a dispute
	 */
	public function testProcessDispute() {
		$processor = new BraintreeAudit();
		$output = $processor->parseFile( __DIR__ . '/../Data/settlement_batch_report_dispute_2022-06-27.json' );
		$this->assertCount( 2, $output, 'Should have found two dispute donations' );
		$actualPaypal = $output[0];
		$expectedPaypal = [
				'gateway' => 'braintree',
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
}
