<?php

namespace SmashPig\PaymentProviders\Gravy\Tests\phpunit;

use SmashPig\PaymentProviders\Gravy\Audit\GravyAudit;
use SmashPig\Tests\BaseSmashPigUnitTestCase;

/**
 * @group Gravy
 * @group Audit
 */
class AuditTest extends BaseSmashPigUnitTestCase {

	public function testCapturedDonationsProcessed(): void {
		$processor = new GravyAudit();
		$output = $processor->parseFile( __DIR__ . '/../Data/settlement_report.csv' );
		// 3 rows in the sample report but only 1 with status 'capture_succeeded'
		$this->assertSame( 1, count( $output ) );

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
			'contribution_tracking_id' => '42'
		];

		$this->assertSame( $expected, $actual );
	}

	public function testEmptyReportNoDonationsProcessed(): void {
		$processor = new GravyAudit();
		$output = $processor->parseFile( __DIR__ . '/../Data/settlement_repor_no_captured_trnxs.csv' );
		// 2 rows in the sample report but non with expected capture_succeeded status.
		$this->assertEmpty( $output );
	}

}
