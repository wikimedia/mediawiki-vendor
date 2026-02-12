<?php
declare( strict_types=1 );

namespace SmashPig\PaymentProviders\Trustly\Test;

require_once 'AuditTestBase.php';

/**
 * Verify Trustly audit file normalization functions
 *
 * @group PayPal
 * @group Audit
 */
class SettlementAuditTest extends AuditTestBase {

	public function testProcessRecFile(): void {
		$output = $this->processFile( 'P11KREC-3618-20260201120000-20260202120000-0001of0001.csv' );
		$this->assertCount( 0, $output, 'Should have found one row' );
	}

	public function testProcessFunFile(): void {
		$output = $this->processFile( 'P11KFUN-3618-20260201120000-20260202120000-0001of0001.csv' );
		$this->assertCount( 3, $output, 'Should have found two valid rows and a payout' );
	}
}
