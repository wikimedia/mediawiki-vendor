<?php

namespace SmashPig\PaymentProviders\Gravy\Tests\phpunit;

use SmashPig\PaymentProviders\Gravy\Audit\GravyAudit;
use SmashPig\Tests\BaseSmashPigUnitTestCase;

/**
 * @group Gravy
 * @group Audit
 */
class AuditTest extends BaseSmashPigUnitTestCase {

	public function testDonationsProcessed(): void {
		$processor = new GravyAudit();
		$output = $processor->parseFile( __DIR__ . '/../Data/settlement_report.csv' );
		// 3 rows in the sample report but only 1 with status 'capture_succeeded'
		$this->assertCount( 1, $output );

		$actual = $output['0'];
		$expected = [
			'gateway' => 'gravy',
			'order_id' => '42.19',
			'currency' => 'USD',
			'date' => 1723150000,
			'gateway_txn_id' => '8177b746-e22c-48cb-a058-11bcae1dcee3',
			'gross' => 10,
			'invoice_id' => '42.19',
			'payment_method' => 'visa',
			'email' => 'test@example.com',
			'first_name' => 'Jimmy',
			'last_name' => 'Wales',
			'settled_gross' => 10,
			'settled_currency' => 'USD',
			'gross_currency' => 'USD',
			'contribution_tracking_id' => '42',
			'backend_processor' => 'adyen'
		];

		$this->assertSame( $expected, $actual );
	}

	public function testEmptyReportNoDonationsProcessed(): void {
		$processor = new GravyAudit();
		$output = $processor->parseFile( __DIR__ . '/../Data/settlement_repor_no_captured_trnxs.csv' );
		// 2 rows in the sample report but non with expected capture_succeeded status.
		$this->assertCount( 0, $output );
	}

	public function testRefundsProcessed(): void {
		$processor = new GravyAudit();
		$output = $processor->parseFile( __DIR__ . '/../Data/settlement_report_refund.csv' );

		// A refund should generate two rows. One for the payment and one for the refund.
		// Gravy combine these two events, so we split them out when parsing the file.
		$this->assertCount( 2, $output );

		$actualPaymentRow = $output['0'];
		$expectedPaymentRow = [
			'gateway' => 'gravy',
			'order_id' => '42.19',
			'currency' => 'USD',
			'date' => 1723150000,
			'gateway_txn_id' => '8177b746-e22c-48cb-a058-11bcae1dcee3',
			'gross' => 10,
			'invoice_id' => '42.19',
			'payment_method' => 'visa',
			'email' => 'test@example.com',
			'first_name' => 'Jimmy',
			'last_name' => 'Wales',
			'settled_gross' => 10,
			'settled_currency' => 'USD',
			'gross_currency' => 'USD',
			'contribution_tracking_id' => '42',
			'backend_processor' => 'adyen'
		];
		$this->assertSame( $expectedPaymentRow, $actualPaymentRow );

		$actualRefundRow = $output['1'];
		$expectedRefundRow = [
			'gateway' => 'gravy',
			'order_id' => '42.19',
			'currency' => 'USD',
			'date' => 1723409202, // the date should have changed to the 'updated_at' timestamp
			'gateway_txn_id' => '8177b746-e22c-48cb-a058-11bcae1dcee3',
			'gross' => 10,
			'invoice_id' => '42.19',
			'payment_method' => 'visa',
			'email' => 'test@example.com',
			'first_name' => 'Jimmy',
			'last_name' => 'Wales',
			'settled_gross' => 10,
			'settled_currency' => 'USD',
			'gross_currency' => 'USD',
			'contribution_tracking_id' => '42',
			'backend_processor' => 'adyen',
			'type' => 'refund'
		];
		$this->assertSame( $expectedRefundRow, $actualRefundRow );
	}

}
