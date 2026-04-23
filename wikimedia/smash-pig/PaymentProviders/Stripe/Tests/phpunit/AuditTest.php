<?php

namespace SmashPig\PaymentProviders\Stripe\Test;

use SmashPig\PaymentProviders\Stripe\Audit\StripeAudit;
use SmashPig\Tests\BaseSmashPigUnitTestCase;

/**
 * @group Audit
 * @group Stripe
 */
class AuditTest extends BaseSmashPigUnitTestCase {

	public function testParseSettlementReportRunCsv(): void {
		$processor = new StripeAudit();
		$output = $processor->parseFile( __DIR__ . '/../Data/settlement_report.csv' );

		$this->assertCount( 6, $output );

		// Some generic ones.
		$this->assertSame( 'stripe', $output[0]['audit_file_gateway'] );
		$this->assertSame( 'stripe', $output[0]['backend_processor'] );
		$this->assertSame( 'acct_live', $output[0]['gateway_account'] );

		// Check order ID from any of the 3 places it might be in.
		$this->assertSame( '24315.1', $output[0]['order_id'] );
		$this->assertSame( '24316.1', $output[1]['order_id'] );
		$this->assertSame( '24317.1', $output[2]['order_id'] );

		// Check payment_orchestrator_reconciliation_id from either of the 3 places it might be in.
		$this->assertSame( '', $output[0]['payment_orchestrator_reconciliation_id'] );
		$this->assertSame( 'abcd', $output[1]['payment_orchestrator_reconciliation_id'] );
		$this->assertSame( 'efgh', $output[2]['payment_orchestrator_reconciliation_id'] );

		$this->assertSame( '00000000-0000-0000-0000-000000851fcf', $output[1]['gateway_txn_id'] );
		$this->assertSame( '00000000-0000-0000-0000-00000093e8bb', $output[2]['gateway_txn_id'] );
		$this->assertSame( 'pi_123', $output[0]['backend_processor_txn_id'] );

		$this->assertSame( 'donation', $output[0]['type'] );
		$this->assertSame( 'po_test123', $output[0]['settlement_batch_reference'] );
		$this->assertSame( '-0.95', $output[0]['settled_fee_amount'] );
		$this->assertSame( '24.05', $output[0]['settled_net_amount'] );
		$this->assertSame( 'USD', $output[0]['settled_currency'] );
		$this->assertArrayNotHasKey( 'balance_transaction_id', $output[0] );
		$this->assertArrayNotHasKey( 'source_id', $output[0] );
		$this->assertArrayNotHasKey( 'reporting_category', $output[0] );
		$this->assertSame( 'refund', $output[1]['type'] );

		// Check out the fee rows
		$this->assertSame( 'fee', $output[3]['type'] );
		$this->assertSame( '-0.5', $output[3]['settled_fee_amount'] );
		$this->assertSame( '-0.5', $output[3]['settled_net_amount'] );
		$this->assertSame( '0.0', $output[3]['settled_total_amount'] );
		$this->assertSame( 'stripe', $output[3]['gateway'] );
		$this->assertSame( 'stripe', $output[3]['backend_processor'] );
		$this->assertSame( 'txn_fee_01', $output[3]['backend_processor_txn_id'] );
		$this->assertSame( 'po_test123', $output[3]['settlement_batch_reference'] );
		$this->assertSame( 'fee', $output[4]['type'] );
		$this->assertSame( 'payout', $output[5]['type'] );
	}

	public function testParsePaymentsActivityCsv(): void {
		$processor = new StripeAudit();
		$output = $processor->parseFile( __DIR__ . '/../Data/payments_activity.csv' );

		$this->assertCount( 2, $output );
		$this->assertSame( '24315.1', $output[0]['order_id'] );
		$this->assertSame( 'pi_aaa', $output[0]['backend_processor_txn_id'] );
		$this->assertSame( 'stripe', $output[0]['backend_processor'] );
		$this->assertSame( 'acct_live', $output[0]['gateway_account'] );
		$this->assertSame( 'chargeback', $output[1]['type'] );
	}
}
