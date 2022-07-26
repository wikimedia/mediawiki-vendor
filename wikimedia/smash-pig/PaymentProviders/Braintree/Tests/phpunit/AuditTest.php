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
		$this->assertSame( 1, count( $output ), 'Should have found one donation' );
		$actual = $output[0];
		$expected = [
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
		$this->assertEquals( $expected, $actual, 'Did not parse donation correctly' );
	}

	/**
	 * Now try a refund
	 */
	public function testProcessRefund() {
		$processor = new BraintreeAudit();
		$output = $processor->parseFile( __DIR__ . '/../Data/settlement_batch_report_refund_2022-06-27.json' );
		$this->assertSame( 1, count( $output ), 'Should have found one refund donation' );
		$actual = $output[0];
		$expected = [
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
		$this->assertEquals( $expected, $actual, 'Did not parse refund correctly' );
	}

	/**
	 * And a dispute
	 */
	public function testProcessDispute() {
		$processor = new BraintreeAudit();
		$output = $processor->parseFile( __DIR__ . '/../Data/settlement_batch_report_dispute_2022-06-27.json' );
		$this->assertSame( 1, count( $output ), 'Should have found one refund donation' );
		$actual = $output[0];
		$expected = [
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
		$this->assertEquals( $expected, $actual, 'Did not parse refund correctly' );
	}
}
